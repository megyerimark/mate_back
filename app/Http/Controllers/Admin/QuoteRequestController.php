<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\UpdateQuoteRequest;
use App\Http\Resources\QuoteRequestResource;
use App\Models\QuoteRequest;
use App\Support\QuoteMailer;
use App\Support\XlsxWriter;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Response;
use Illuminate\Support\Carbon;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rule;
use Symfony\Component\HttpFoundation\StreamedResponse;

class QuoteRequestController extends Controller
{
    public function index(Request $request): AnonymousResourceCollection
    {
        $filters = $this->filters($request, ['per_page' => ['nullable', 'integer', 'min:5', 'max:100']]);

        return QuoteRequestResource::collection(
            $this->filtered($filters)->with('documents')->latest()->paginate($filters['per_page'] ?? 20),
        );
    }

    /** Excel export a lista szűrőivel (állapot, keresés, dátumtartomány). */
    public function export(Request $request): Response
    {
        $filters = $this->filters($request);
        $quotes = $this->filtered($filters)->with('documents')->latest()->get();

        $xlsx = (new XlsxWriter('Ajánlatkérések'))->columns([
            ['title' => 'Azonosító', 'width' => 11],
            ['title' => 'Beérkezett', 'width' => 18],
            ['title' => 'Név', 'width' => 24],
            ['title' => 'E-mail', 'width' => 30],
            ['title' => 'Telefon', 'width' => 17],
            ['title' => 'Szolgáltatás', 'width' => 34],
            ['title' => 'Nyelv(ek)', 'width' => 22],
            ['title' => 'Határidő', 'width' => 24],
            ['title' => 'Weboldal', 'width' => 10],
            ['title' => 'Állapot', 'width' => 17],
            ['title' => 'Ár (Ft)', 'width' => 13],
            ['title' => 'Dokumentum', 'width' => 26],
            ['title' => 'Az ügyfél üzenete', 'width' => 50],
            ['title' => 'Belső jegyzet', 'width' => 40],
            ['title' => 'Utoljára módosítva', 'width' => 18],
        ]);

        foreach ($quotes as $q) {
            $xlsx->addRow([
                $q->id,
                $q->created_at,
                $q->name,
                $q->email,
                $q->phone,
                $q->serviceLabel(),
                trim(($q->source_language ? $q->source_language.' → ' : '').($q->target_language ?? '')),
                $q->service === 'spelling' ? null : $q->deadlineLabel(),
                strtoupper($q->locale ?? 'hu'),
                $q->statusLabel(),
                $q->price,
                collect($q->files())->pluck('name')->join(', ') ?: null,
                $q->message,
                $q->admin_note,
                $q->updated_at,
            ]);
        }

        $name = 'ajanlatkeresek-'.now()->format('Y-m-d').'.xlsx';

        return response($xlsx->toString(), 200, [
            'Content-Type' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
            'Content-Disposition' => 'attachment; filename="'.$name.'"',
            'Access-Control-Expose-Headers' => 'Content-Disposition',
            'Cache-Control' => 'no-store',
        ]);
    }

    /** @return array<string, mixed> */
    private function filters(Request $request, array $extra = []): array
    {
        return $request->validate([
            'status' => ['nullable', Rule::in(array_keys(QuoteRequest::STATUSES))],
            'search' => ['nullable', 'string', 'max:100'],
            'from' => ['nullable', 'date'],
            'to' => ['nullable', 'date', 'after_or_equal:from'],
            'follow_up' => ['nullable', 'boolean'],
            ...$extra,
        ]);
    }

    private function filtered(array $filters): Builder
    {
        return QuoteRequest::query()
            ->when($filters['status'] ?? null, fn ($q, $status) => $q->where('status', $status))
            ->when($filters['follow_up'] ?? false, fn ($q) => $q->awaitingFollowUp())
            ->when($filters['from'] ?? null, fn ($q, $from) => $q->where('created_at', '>=', Carbon::parse($from, config('app.timezone'))->startOfDay()))
            ->when($filters['to'] ?? null, fn ($q, $to) => $q->where('created_at', '<=', Carbon::parse($to, config('app.timezone'))->endOfDay()))
            ->when($filters['search'] ?? null, function ($q, $search) {
                $like = '%'.$search.'%';
                $q->where(fn ($q) => $q
                    ->where('name', 'like', $like)
                    ->orWhere('email', 'like', $like)
                    ->orWhere('phone', 'like', $like)
                    ->orWhere('message', 'like', $like));
            });
    }

    public function show(QuoteRequest $quoteRequest): QuoteRequestResource
    {
        return new QuoteRequestResource($quoteRequest->load('events.user'));
    }

    public function update(UpdateQuoteRequest $request, QuoteRequest $quoteRequest): QuoteRequestResource
    {
        $changes = $request->validated();

        DB::transaction(function () use ($changes, $quoteRequest, $request) {
            // Előzmények: csak a ténylegesen megváltozott mezők kerülnek bele.
            $log = fn (string $type, $from, $to) => $quoteRequest->events()->create([
                'user_id' => $request->user()?->id, 'type' => $type, 'from' => $from, 'to' => $to,
            ]);

            if (array_key_exists('status', $changes) && $changes['status'] !== $quoteRequest->status) {
                $log('status', $quoteRequest->status, $changes['status']);
                $quoteRequest->status_changed_at = now();
            }
            if (array_key_exists('price', $changes) && $changes['price'] !== null) {
                $changes['price'] = (int) $changes['price'];
            }
            if (array_key_exists('price', $changes) && $changes['price'] !== $quoteRequest->price) {
                $log('price', $quoteRequest->price, $changes['price']);
            }
            if (array_key_exists('admin_note', $changes) && ($changes['admin_note'] ?? '') !== ($quoteRequest->admin_note ?? '')) {
                $log('note', null, null);
            }

            $quoteRequest->forceFill($changes)->save();
        });

        return new QuoteRequestResource($quoteRequest->load('events.user'));
    }

    /** A ki nem ment levelek újraküldése (vagy a megadott: notify = irodának, confirm = ügyfélnek). */
    public function resendMail(Request $request, QuoteRequest $quoteRequest, QuoteMailer $mailer): QuoteRequestResource
    {
        $which = $request->validate(['which' => ['required', 'array'], 'which.*' => [Rule::in(['notify', 'confirm'])]])['which'];
        $mailer->send($quoteRequest, $which);

        return new QuoteRequestResource($quoteRequest->load('events.user'));
    }

    public function destroy(QuoteRequest $quoteRequest): JsonResponse
    {
        Storage::disk('local')->delete(array_column($quoteRequest->files(), 'path'));
        $quoteRequest->delete();

        return response()->json(['message' => 'Törölve.']);
    }

    /** A feltöltött fájlok egyike (sorszám szerint); a régi, sorszám nélküli útvonal az elsőt adja. */
    public function document(QuoteRequest $quoteRequest, int $index = 0): StreamedResponse
    {
        $file = $quoteRequest->files()[$index] ?? null;
        abort_unless($file && Storage::disk('local')->exists($file['path']), 404, 'Ehhez az ajánlatkéréshez nincs ilyen dokumentum.');

        return Storage::disk('local')->download($file['path'], $file['name']);
    }

    /** Összesítő számok a vezérlőpult tetejére. */
    public function stats(): JsonResponse
    {
        $byStatus = QuoteRequest::query()
            ->selectRaw('status, count(*) as total')
            ->groupBy('status')
            ->pluck('total', 'status');

        return response()->json([
            'total' => (int) $byStatus->sum(),
            'follow_up' => QuoteRequest::awaitingFollowUp()->count(),
            'this_month' => QuoteRequest::where('created_at', '>=', now()->startOfMonth())->count(),
            'by_status' => collect(QuoteRequest::STATUSES)
                ->map(fn ($label, $key) => ['status' => $key, 'label' => $label, 'total' => (int) ($byStatus[$key] ?? 0)])
                ->values(),
        ]);
    }
}

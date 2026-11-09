<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\QuoteRequest;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Validation\Rule;

/** Statisztika az admin felületre: a weboldalon beérkezett ajánlatkérésekből. */
class StatsController extends Controller
{
    private const MONTHS_HU = ['jan.', 'febr.', 'márc.', 'ápr.', 'máj.', 'jún.', 'júl.', 'aug.', 'szept.', 'okt.', 'nov.', 'dec.'];

    public function __invoke(Request $request): JsonResponse
    {
        $period = $request->validate(['period' => ['nullable', Rule::in(['12m', 'year', 'all'])]])['period'] ?? '12m';
        $tz = config('app.timezone');
        $now = Carbon::now($tz);

        $to = $now->copy()->endOfMonth();
        $from = match ($period) {
            'year' => $now->copy()->startOfYear(),
            'all' => Carbon::parse(QuoteRequest::min('created_at') ?? $now, 'UTC')->setTimezone($tz)->startOfMonth()
                ->max($now->copy()->subMonths(59)->startOfMonth()),
            default => $now->copy()->subMonths(11)->startOfMonth(),
        };

        // Kis adatmennyiség: a csoportosítás PHP-ban történik, így bármilyen adatbázissal működik.
        $quotes = QuoteRequest::query()
            ->whereBetween('created_at', [$from->copy()->utc(), $to->copy()->utc()])
            ->get(['id', 'created_at', 'service', 'status', 'locale', 'deadline', 'target_language']);

        $won = $quotes->where('status', 'won')->count();
        $lost = $quotes->where('status', 'lost')->count();

        return response()->json([
            'period' => $period,
            'from' => $from->toDateString(),
            'to' => $to->toDateString(),
            'totals' => [
                'total' => $quotes->count(),
                'won' => $won,
                'lost' => $lost,
                'open' => $quotes->whereIn('status', ['new', 'in_progress', 'quoted'])->count(),
                // Megrendelési arány a lezárt (megrendelt vagy elmaradt) ajánlatkérésekből
                'conversion' => $won + $lost > 0 ? round($won / ($won + $lost) * 100) : null,
                'english' => $quotes->where('locale', 'en')->count(),
            ],
            'this_month' => QuoteRequest::where('created_at', '>=', $now->copy()->startOfMonth()->utc())->count(),
            'last_month' => QuoteRequest::whereBetween('created_at', [
                $now->copy()->subMonthNoOverflow()->startOfMonth()->utc(),
                $now->copy()->subMonthNoOverflow()->endOfMonth()->utc(),
            ])->count(),
            'monthly' => $this->monthly($quotes, $from, $to, $tz),
            'by_service' => $this->breakdown($quotes, 'service', QuoteRequest::SERVICES),
            'by_status' => $this->breakdown($quotes, 'status', QuoteRequest::STATUSES, keepEmpty: true),
            'by_locale' => $this->breakdown($quotes, 'locale', ['hu' => 'Magyar oldal', 'en' => 'Angol oldal'], keepEmpty: true),
            'by_deadline' => $this->breakdown($quotes->where('service', '!=', 'spelling'), 'deadline', QuoteRequest::DEADLINES),
            'top_languages' => $this->topLanguages($quotes),
        ]);
    }

    private function monthly(Collection $quotes, Carbon $from, Carbon $to, string $tz): array
    {
        $byMonth = $quotes->groupBy(fn ($q) => $q->created_at->copy()->setTimezone($tz)->format('Y-m'));
        $months = [];
        for ($m = $from->copy()->startOfMonth(); $m <= $to; $m->addMonthNoOverflow()) {
            $key = $m->format('Y-m');
            $items = $byMonth->get($key, collect());
            $months[] = [
                'month' => $key,
                'label' => $m->year.'. '.self::MONTHS_HU[$m->month - 1],
                'total' => $items->count(),
                'won' => $items->where('status', 'won')->count(),
            ];
        }

        return $months;
    }

    private function breakdown(Collection $quotes, string $field, array $labels, bool $keepEmpty = false): array
    {
        $counts = $quotes->countBy($field);

        return collect($labels)
            ->map(fn ($label, $key) => ['key' => $key, 'label' => $label, 'total' => (int) ($counts[$key] ?? 0)])
            ->filter(fn ($row) => $keepEmpty || $row['total'] > 0)
            ->sortByDesc('total')
            ->values()
            ->all();
    }

    /** A leggyakoribb célnyelvek (szabad szöveg, kisbetűsítve összevonva), a többi „Egyéb”. */
    private function topLanguages(Collection $quotes): array
    {
        $counts = $quotes
            ->pluck('target_language')
            ->filter(fn ($l) => filled($l))
            ->map(fn ($l) => mb_strtolower(trim($l)))
            ->countBy()
            ->sortDesc();

        $top = $counts->take(8)->map(fn ($total, $label) => ['label' => $label, 'total' => $total])->values();
        $rest = $counts->slice(8)->sum();

        return $rest > 0 ? $top->push(['label' => 'egyéb', 'total' => $rest])->all() : $top->all();
    }
}

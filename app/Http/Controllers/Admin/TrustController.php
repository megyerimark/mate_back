<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Partner;
use App\Models\Testimonial;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rule;

/** Vélemények és partnerek kezelése az adminban. */
class TrustController extends Controller
{
    private const MESSAGES = [
        'name.required' => 'Add meg a nevet.',
        'text.required' => 'Írd be a véleményt.',
        'logo.image' => 'A logó kép legyen (PNG, JPG, SVG vagy WebP).',
        'logo.mimes' => 'A logó PNG, JPG, SVG vagy WebP lehet.',
        'logo.max' => 'A logó legfeljebb 1 MB lehet.',
        'url.url' => 'Érvénytelen webcím (https://...).',
    ];

    public function testimonials(): JsonResponse
    {
        return response()->json(['data' => Testimonial::orderBy('sort')->orderByDesc('id')->get()]);
    }

    public function storeTestimonial(Request $request): JsonResponse
    {
        return response()->json(['data' => Testimonial::create($this->testimonialData($request))], 201);
    }

    public function updateTestimonial(Request $request, Testimonial $testimonial): JsonResponse
    {
        $testimonial->update($this->testimonialData($request, partial: true));

        return response()->json(['data' => $testimonial]);
    }

    public function destroyTestimonial(Testimonial $testimonial): JsonResponse
    {
        $testimonial->delete();

        return response()->json(['message' => 'Törölve.']);
    }

    public function partners(): JsonResponse
    {
        return response()->json(['data' => Partner::orderBy('sort')->orderBy('name')->get()->map(fn (Partner $p) => $this->partnerJson($p))]);
    }

    public function storePartner(Request $request): JsonResponse
    {
        $partner = new Partner($this->partnerData($request));
        $this->storeLogo($request, $partner);
        $partner->save();

        return response()->json(['data' => $this->partnerJson($partner)], 201);
    }

    /** Multipart (logócsere) miatt POST. */
    public function updatePartner(Request $request, Partner $partner): JsonResponse
    {
        $partner->fill($this->partnerData($request, partial: true));
        $this->storeLogo($request, $partner);
        $partner->save();

        return response()->json(['data' => $this->partnerJson($partner)]);
    }

    public function destroyPartner(Partner $partner): JsonResponse
    {
        if ($partner->logo_path) {
            Storage::disk('local')->delete($partner->logo_path);
        }
        $partner->delete();

        return response()->json(['message' => 'Törölve.']);
    }

    /** Az admin előnézethez (publikálatlan logó is). */
    public function partnerLogo(Partner $partner)
    {
        abort_unless($partner->logo_path && Storage::disk('local')->exists($partner->logo_path), 404);

        return Storage::disk('local')->response($partner->logo_path);
    }

    private function testimonialData(Request $request, bool $partial = false): array
    {
        $req = $partial ? 'sometimes' : 'required';

        return $request->validate([
            'name' => [$req, 'string', 'max:120'],
            'company' => ['nullable', 'string', 'max:160'],
            'role' => ['nullable', 'string', 'max:120'],
            'text' => [$req, 'string', 'max:2000'],
            'locale' => ['sometimes', Rule::in(['hu', 'en', 'all'])],
            'rating' => ['nullable', 'integer', 'between:1,5'],
            'is_published' => ['sometimes', 'boolean'],
            'sort' => ['sometimes', 'integer', 'between:-1000,1000'],
        ], self::MESSAGES);
    }

    private function partnerData(Request $request, bool $partial = false): array
    {
        return collect($request->validate([
            'name' => [$partial ? 'sometimes' : 'required', 'string', 'max:160'],
            'url' => ['nullable', 'url', 'max:255'],
            'is_published' => ['sometimes', 'boolean'],
            'sort' => ['sometimes', 'integer', 'between:-1000,1000'],
            'logo' => [$partial ? 'nullable' : 'required', 'file', 'mimes:png,jpg,jpeg,svg,webp', 'max:1024'],
        ], self::MESSAGES + ['logo.required' => 'Tölts fel logót.']))->except('logo')->all();
    }

    private function storeLogo(Request $request, Partner $partner): void
    {
        if ($file = $request->file('logo')) {
            if ($partner->logo_path) {
                Storage::disk('local')->delete($partner->logo_path);
            }
            $partner->logo_path = $file->store('partners', 'local');
        }
    }

    private function partnerJson(Partner $p): array
    {
        return $p->only('id', 'name', 'url', 'is_published', 'sort') + ['logo' => $p->logo_path ? url("/api/admin/partners/{$p->id}/logo") : null];
    }
}

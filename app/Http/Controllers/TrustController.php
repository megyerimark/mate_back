<?php

namespace App\Http\Controllers;

use App\Models\Partner;
use App\Models\Testimonial;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rule;
use Symfony\Component\HttpFoundation\StreamedResponse;

/** A weboldal bizalomépítő szakaszának adatai: csak publikált, valódi vélemények és partnerek. */
class TrustController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $lang = $request->validate(['lang' => ['nullable', Rule::in(['hu', 'en'])]])['lang'] ?? 'hu';

        return response()->json([
            'testimonials' => Testimonial::publishedFor($lang)->get(['id', 'name', 'company', 'role', 'text', 'rating']),
            'partners' => Partner::where('is_published', true)->whereNotNull('logo_path')->orderBy('sort')->orderBy('name')->get()
                ->map(fn (Partner $p) => ['id' => $p->id, 'name' => $p->name, 'url' => $p->url, 'logo' => $p->logoUrl()]),
        ])->header('Cache-Control', 'public, max-age=300');
    }

    public function logo(Partner $partner): StreamedResponse
    {
        abort_unless($partner->is_published && $partner->logo_path && Storage::disk('local')->exists($partner->logo_path), 404);

        return Storage::disk('local')->response($partner->logo_path, null, ['Cache-Control' => 'public, max-age=86400']);
    }
}

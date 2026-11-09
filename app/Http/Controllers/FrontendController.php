<?php

namespace App\Http\Controllers;

use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;

/**
 * Az előrenderelt Angular oldalak kiszolgálása (frontend: npm run build:backend -> resources/frontend).
 * A címek a régi WordPress oldaléval egyeznek, perjellel a végén, hogy a Google-helyezés megmaradjon.
 */
class FrontendController extends Controller
{
    /** A régi WordPress (Yoast) sitemap címei; a Search Console-ban beküldött sitemap így is működik. */
    private const LEGACY_REDIRECTS = [
        'sitemap_index.xml' => '/sitemap.xml',
        'page-sitemap.xml' => '/sitemap.xml',
        'wp-sitemap.xml' => '/sitemap.xml',
    ];

    public function __invoke(Request $request, ?string $path = null): Response|RedirectResponse
    {
        $path = trim((string) $path, '/');
        $dir = resource_path('frontend');

        if (isset(self::LEGACY_REDIRECTS[$path])) {
            return redirect(self::LEGACY_REDIRECTS[$path], 301);
        }

        if ($page = $this->prerendered($dir, $path)) {
            // Perjel nélküli cím -> perjeles (ahogy a WordPress is csinálta)
            if ($path !== '' && ! str_ends_with($request->getPathInfo(), '/')) {
                $query = $request->getQueryString();

                // redirect()->away(): a Laravel URL-generátora levágná a cím végi perjelet.
                return redirect()->away($request->getSchemeAndHttpHost().'/'.$path.'/'.($query ? '?'.$query : ''), 301);
            }

            return $this->html($page);
        }

        // Az admin felület csak böngészőben fut, nincs előrenderelve.
        if ($path === 'admin' || str_starts_with($path, 'admin/')) {
            return $this->html($dir.'/index.csr.html');
        }

        // Ismeretlen cím: valódi 404, az Angular „nem található” oldalával.
        return $this->html($dir.'/index.csr.html', 404);
    }

    private function prerendered(string $dir, string $path): ?string
    {
        $file = realpath($path === '' ? $dir.'/index.html' : $dir.'/'.$path.'/index.html');
        $base = realpath($dir);

        return $file && $base && str_starts_with($file, $base.DIRECTORY_SEPARATOR) && is_file($file) ? $file : null;
    }

    private function html(string $file, int $status = 200): Response
    {
        abort_unless(is_file($file), 404, 'A frontend még nincs lebuildelve: a mate_front mappában futtasd: npm run build:backend');

        return response(file_get_contents($file), $status, [
            'Content-Type' => 'text/html; charset=UTF-8',
            'Cache-Control' => 'no-cache',
        ]);
    }
}

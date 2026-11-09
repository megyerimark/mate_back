<?php

namespace Tests\Feature;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\File;
use Illuminate\Testing\TestResponse;
use Tests\TestCase;

/** Az előrenderelt oldalak kiszolgálása a régi WordPress címeken. */
class FrontendTest extends TestCase
{
    private string $dir;

    private bool $hadBuild;

    protected function setUp(): void
    {
        parent::setUp();
        $this->dir = resource_path('frontend');
        $this->hadBuild = File::isDirectory($this->dir);
        if (! $this->hadBuild) {
            File::ensureDirectoryExists($this->dir.'/forditas');
            File::ensureDirectoryExists($this->dir.'/en/translation');
            File::put($this->dir.'/index.html', '<h1>DONE Translations Fordítóiroda</h1>');
            File::put($this->dir.'/forditas/index.html', '<h1>Fordítás</h1>');
            File::put($this->dir.'/en/translation/index.html', '<h1>Translation</h1>');
            File::put($this->dir.'/index.csr.html', '<app-root></app-root>');
        }
    }

    protected function tearDown(): void
    {
        if (! $this->hadBuild) {
            File::deleteDirectory($this->dir);
        }
        parent::tearDown();
    }

    /** A Laravel tesztkliense levágja a cím végi perjelet, ezért a kérést közvetlenül küldjük, ahogy a böngésző. */
    private function raw(string $uri): TestResponse
    {
        return TestResponse::fromBaseResponse($this->app->handle(Request::create('http://localhost'.$uri)));
    }

    public function test_prerendered_pages_are_served_on_their_old_addresses(): void
    {
        $this->raw('/')->assertOk()->assertSee('DONE Translations Fordítóiroda', false);
        $this->raw('/forditas/')->assertOk()->assertSee('Fordítás', false);
        $this->raw('/en/translation/')->assertOk()->assertSee('Translation', false);
    }

    public function test_addresses_without_trailing_slash_redirect_permanently(): void
    {
        // A pontos Location fejlécet nézzük: az assertRedirect a perjelet levágva hasonlítana.
        $this->raw('/forditas')->assertMovedPermanently()->assertHeader('Location', 'http://localhost/forditas/');
        $this->raw('/forditas?utm_source=google')->assertHeader('Location', 'http://localhost/forditas/?utm_source=google');
    }

    public function test_old_yoast_sitemaps_redirect_to_the_new_sitemap(): void
    {
        $this->get('/sitemap_index.xml')->assertMovedPermanently()->assertRedirect('/sitemap.xml');
        $this->get('/page-sitemap.xml')->assertRedirect('/sitemap.xml');
    }

    public function test_unknown_address_is_a_real_404_and_admin_is_client_rendered(): void
    {
        $this->raw('/nincs-ilyen-oldal/')->assertNotFound();
        $this->get('/../../.env')->assertNotFound();
        $this->get('/admin')->assertOk();
        $this->get('/admin/login')->assertOk();
        $this->get('/api/nem-letezik')->assertNotFound();
    }
}

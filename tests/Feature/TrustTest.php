<?php

namespace Tests\Feature;

use App\Models\Partner;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class TrustTest extends TestCase
{
    use RefreshDatabase;

    public function test_public_endpoint_is_empty_until_something_is_published(): void
    {
        $this->getJson('/api/trust')->assertOk()->assertExactJson(['testimonials' => [], 'partners' => []]);
    }

    public function test_admin_manages_testimonials_and_only_published_ones_in_the_right_language_show(): void
    {
        $this->postJson('/api/admin/testimonials', ['name' => 'X', 'text' => 'Y'])->assertUnauthorized();
        Sanctum::actingAs(User::factory()->create());

        $id = $this->postJson('/api/admin/testimonials', ['name' => 'Kovács Anna', 'company' => 'Példa Kft.', 'text' => 'Gyors és pontos.', 'locale' => 'hu'])
            ->assertCreated()->json('data.id');
        $this->postJson('/api/admin/testimonials', ['name' => 'John', 'text' => 'Great.', 'locale' => 'en', 'is_published' => true])->assertCreated();
        $this->postJson('/api/admin/testimonials', ['name' => '', 'text' => ''])->assertJsonValidationErrors(['name', 'text']);

        $this->getJson('/api/trust?lang=hu')->assertJsonCount(0, 'testimonials');
        $this->patchJson("/api/admin/testimonials/{$id}", ['is_published' => true])->assertOk();
        $this->getJson('/api/trust?lang=hu')->assertJsonCount(1, 'testimonials')->assertJsonPath('testimonials.0.name', 'Kovács Anna');
        $this->getJson('/api/trust?lang=en')->assertJsonCount(1, 'testimonials')->assertJsonPath('testimonials.0.name', 'John');

        $this->deleteJson("/api/admin/testimonials/{$id}")->assertOk();
        $this->getJson('/api/trust?lang=hu')->assertJsonCount(0, 'testimonials');
    }

    public function test_partner_logo_is_uploaded_served_when_published_and_deleted_with_the_partner(): void
    {
        Storage::fake('local');
        Sanctum::actingAs(User::factory()->create());

        $this->post('/api/admin/partners', ['name' => 'Logó nélkül'], ['Accept' => 'application/json'])->assertJsonValidationErrors('logo');
        $this->post('/api/admin/partners', ['name' => 'Rossz', 'logo' => UploadedFile::fake()->create('x.exe', 5)], ['Accept' => 'application/json'])
            ->assertJsonValidationErrors('logo');

        $id = $this->post('/api/admin/partners', ['name' => 'Példa Zrt.', 'url' => 'https://example.com', 'logo' => UploadedFile::fake()->image('logo.png', 200, 80)], ['Accept' => 'application/json'])
            ->assertCreated()->json('data.id');
        $partner = Partner::find($id);

        $this->get("/api/partners/{$id}/logo")->assertNotFound(); // még nincs publikálva
        $this->post("/api/admin/partners/{$id}", ['is_published' => '1'], ['Accept' => 'application/json'])->assertOk();
        $this->getJson('/api/trust')->assertJsonPath('partners.0.name', 'Példa Zrt.');
        $this->get("/api/partners/{$id}/logo")->assertOk();

        $this->deleteJson("/api/admin/partners/{$id}")->assertOk();
        Storage::disk('local')->assertMissing($partner->logo_path);
    }
}

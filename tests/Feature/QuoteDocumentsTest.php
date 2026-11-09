<?php

namespace Tests\Feature;

use App\Mail\QuoteRequestConfirmation;
use App\Mail\QuoteRequestReceived;
use App\Models\QuoteRequest;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Storage;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class QuoteDocumentsTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Mail::fake();
        Storage::fake('local');
    }

    private function payload(array $extra = []): array
    {
        return ['name' => 'Teszt Elek', 'email' => 'elek@example.com', 'deadline' => 'normal', 'privacy_accepted' => '1'] + $extra;
    }

    public function test_several_files_are_stored_attached_and_downloadable(): void
    {
        $this->post('/api/quote-requests', $this->payload(['documents' => [
            UploadedFile::fake()->create('szerzodes.pdf', 300, 'application/pdf'),
            UploadedFile::fake()->image('oldal-2.jpg'),
        ]]))->assertCreated();

        $quote = QuoteRequest::sole();
        $this->assertSame(['szerzodes.pdf', 'oldal-2.jpg'], array_column($quote->files(), 'name'));
        Mail::assertSent(QuoteRequestReceived::class, fn ($m) => count($m->attachments()) === 2);
        Mail::assertSent(QuoteRequestConfirmation::class, function ($m) {
            $m->assertSeeInText('A csatolt dokumentumok (szerzodes.pdf, oldal-2.jpg) rendben megérkezett.');

            return true;
        });

        Sanctum::actingAs(User::factory()->create());
        $this->getJson("/api/admin/quote-requests/{$quote->id}")
            ->assertJsonCount(2, 'data.documents')
            ->assertJsonPath('data.documents.1.name', 'oldal-2.jpg')
            ->assertJsonPath('data.has_document', true);
        $this->get("/api/admin/quote-requests/{$quote->id}/documents/1")->assertOk()->assertDownload('oldal-2.jpg');
        $this->get("/api/admin/quote-requests/{$quote->id}/documents/5")->assertNotFound();

        // Törléskor a fájlok is eltűnnek.
        $paths = array_column($quote->files(), 'path');
        $this->deleteJson("/api/admin/quote-requests/{$quote->id}")->assertOk();
        foreach ($paths as $p) {
            Storage::disk('local')->assertMissing($p);
        }
    }

    public function test_file_count_type_and_total_size_are_validated(): void
    {
        $six = array_map(fn ($i) => UploadedFile::fake()->create("f{$i}.pdf", 10, 'application/pdf'), range(1, 6));
        $this->postJson('/api/quote-requests', $this->payload(['documents' => $six]))
            ->assertJsonValidationErrors(['documents' => 'Legfeljebb 5 fájl']);

        $this->postJson('/api/quote-requests', $this->payload(['documents' => [UploadedFile::fake()->create('virus.exe', 10)]]))
            ->assertJsonValidationErrors(['documents.0' => 'nem támogatott']);

        config(['quotes.max_total_kb' => 1000]);
        $this->postJson('/api/quote-requests', $this->payload(['locale' => 'en', 'documents' => [
            UploadedFile::fake()->create('a.pdf', 600, 'application/pdf'),
            UploadedFile::fake()->create('b.pdf', 600, 'application/pdf'),
        ]]))->assertJsonValidationErrors(['documents' => 'files together may be at most']);

        $this->assertSame(0, QuoteRequest::count());
    }

    public function test_requests_from_before_the_change_keep_their_single_file(): void
    {
        Storage::disk('local')->put('quote-requests/regi.pdf', 'pdf');
        $q = QuoteRequest::create(['name' => 'Régi', 'email' => 'regi@example.com', 'deadline' => 'normal']);
        $q->forceFill(['document_path' => 'quote-requests/regi.pdf', 'document_name' => 'regi.pdf'])->save();

        Sanctum::actingAs(User::factory()->create());
        $this->getJson("/api/admin/quote-requests/{$q->id}")->assertJsonPath('data.documents.0.name', 'regi.pdf');
        $this->get("/api/admin/quote-requests/{$q->id}/document")->assertDownload('regi.pdf');
    }
}

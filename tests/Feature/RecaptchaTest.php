<?php

namespace Tests\Feature;

use App\Models\QuoteRequest;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Mail;
use Tests\TestCase;

class RecaptchaTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Mail::fake();
        config(['services.recaptcha.secret' => 'titkos', 'services.recaptcha.min_score' => 0.5]);
    }

    private function payload(array $overrides = []): array
    {
        return array_merge([
            'name' => 'Teszt Elek',
            'email' => 'elek@example.com',
            'deadline' => 'normal',
            'privacy_accepted' => '1',
            'recaptcha_token' => 'token-123',
        ], $overrides);
    }

    private function google(array $answer): void
    {
        Http::fake(['www.google.com/recaptcha/api/siteverify' => Http::response($answer)]);
    }

    public function test_human_passes_and_the_token_is_sent_to_google(): void
    {
        $this->google(['success' => true, 'score' => 0.9, 'action' => 'quote']);

        $this->postJson('/api/quote-requests', $this->payload())->assertCreated();

        $this->assertSame(1, QuoteRequest::count());
        Http::assertSent(fn ($r) => $r['secret'] === 'titkos' && $r['response'] === 'token-123');
    }

    public function test_bot_score_is_rejected_with_a_localized_message(): void
    {
        $this->google(['success' => true, 'score' => 0.1, 'action' => 'quote']);

        $this->postJson('/api/quote-requests', $this->payload())
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['recaptcha_token' => 'biztonsági ellenőrzés']);

        $this->postJson('/api/quote-requests', $this->payload(['locale' => 'en']))
            ->assertJsonValidationErrors(['recaptcha_token' => 'security check']);

        $this->assertSame(0, QuoteRequest::count());
    }

    public function test_wrong_action_or_missing_token_is_rejected(): void
    {
        $this->google(['success' => true, 'score' => 0.9, 'action' => 'login']);
        $this->postJson('/api/quote-requests', $this->payload())->assertJsonValidationErrors('recaptcha_token');

        $this->postJson('/api/quote-requests', $this->payload(['recaptcha_token' => null]))->assertJsonValidationErrors('recaptcha_token');
    }

    public function test_google_outage_does_not_lose_the_client(): void
    {
        Http::fake(fn () => throw new ConnectionException('timeout'));

        $this->postJson('/api/quote-requests', $this->payload())->assertCreated();
    }

    public function test_without_a_secret_key_the_check_is_off(): void
    {
        config(['services.recaptcha.secret' => null]);
        Http::fake();

        $this->postJson('/api/quote-requests', $this->payload(['recaptcha_token' => null]))->assertCreated();
        Http::assertNothingSent();
    }
}

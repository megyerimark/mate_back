<?php

namespace Tests\Feature;

use App\Mail\QuoteRequestConfirmation;
use App\Mail\QuoteRequestReceived;
use App\Models\QuoteRequest;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Laravel\Sanctum\Sanctum;
use RuntimeException;
use Tests\TestCase;

class QuoteMailStatusTest extends TestCase
{
    use RefreshDatabase;

    private function submit(): QuoteRequest
    {
        $this->postJson('/api/quote-requests', [
            'name' => 'Teszt Elek', 'email' => 'elek@example.com', 'deadline' => 'normal', 'privacy_accepted' => '1',
        ])->assertCreated();

        return QuoteRequest::sole();
    }

    public function test_successful_mails_are_recorded(): void
    {
        Mail::fake();
        config(['quotes.recipient' => 'iroda@example.com']);
        $q = $this->submit();

        $this->assertNotNull($q->notified_at);
        $this->assertNotNull($q->confirmed_at);
        $this->assertNull($q->mail_error);
        Mail::assertSent(QuoteRequestReceived::class, fn ($m) => $m->hasTo('iroda@example.com'));
    }

    public function test_a_failing_client_confirmation_does_not_stop_the_office_notification_and_can_be_resent(): void
    {
        Mail::fake();
        // Az ügyfélnek szóló levél elhasal (pl. elgépelt cím), az irodának szóló kimegy.
        Mail::shouldReceive('to')->andReturnUsing(function ($to) {
            return new class($to) {
                public function __construct(private $to) {}

                public function send($mail)
                {
                    if ($mail instanceof QuoteRequestConfirmation) {
                        throw new RuntimeException('550 mailbox unavailable');
                    }
                }
            };
        });

        $q = $this->submit();
        $this->assertNotNull($q->notified_at);
        $this->assertNull($q->confirmed_at);
        $this->assertStringContainsString('550 mailbox unavailable', $q->mail_error);

        // Az adminban látszik, és újraküldhető.
        app()->forgetInstance('mail.manager');
        Mail::clearResolvedInstances();
        Mail::fake();
        Sanctum::actingAs(User::factory()->create());
        $this->getJson("/api/admin/quote-requests/{$q->id}")->assertJsonPath('data.mail_error', $q->mail_error);
        $this->postJson("/api/admin/quote-requests/{$q->id}/resend-mail", ['which' => ['confirm']])
            ->assertOk()
            ->assertJsonPath('data.mail_error', null)
            ->assertJsonPath('data.confirmed_at', fn ($v) => $v !== null);
        Mail::assertSent(QuoteRequestConfirmation::class);
        Mail::assertNotSent(QuoteRequestReceived::class);
    }

    public function test_empty_recipient_in_env_falls_back_to_the_sender_address(): void
    {
        $backup = [$_ENV, $_SERVER];
        foreach (['QUOTE_RECIPIENT_EMAIL' => '', 'MAIL_FROM_ADDRESS' => 'info@example.com'] as $k => $v) {
            $_ENV[$k] = $_SERVER[$k] = $v;
            putenv("{$k}={$v}");
        }
        $config = require base_path('config/quotes.php');
        [$_ENV, $_SERVER] = $backup;
        putenv('QUOTE_RECIPIENT_EMAIL');
        putenv('MAIL_FROM_ADDRESS');

        $this->assertSame('info@example.com', $config['recipient']);
    }

    public function test_mail_test_command_sends_a_message(): void
    {
        Mail::fake();
        $this->artisan('mail:test', ['to' => 'proba@example.com'])
            ->expectsOutputToContain('Címzett: proba@example.com')
            ->expectsOutputToContain('Elküldve')
            ->assertSuccessful();
    }
}

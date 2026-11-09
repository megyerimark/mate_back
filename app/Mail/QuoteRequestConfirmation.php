<?php

namespace App\Mail;

use App\Models\QuoteRequest;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Str;

/** Automatikus visszaigazolás az ügyfélnek. */
class QuoteRequestConfirmation extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(public QuoteRequest $quote) {}

    public function envelope(): Envelope
    {
        $subject = $this->quote->locale === 'en'
            ? 'We have received your request – '.config('app.name')
            : 'Megkaptuk az ajánlatkérését – '.config('app.name');

        return new Envelope(subject: $subject);
    }

    public function content(): Content
    {
        // Az ügyfél a weboldal nyelvén kapja a visszaigazolást.
        return new Content(
            markdown: $this->quote->locale === 'en'
                ? 'mail.quote-request-confirmation-en'
                : 'mail.quote-request-confirmation',
            with: ['summary' => $this->summary()],
        );
    }

    private const EN = [
        'labels' => ['service' => 'Service', 'languages' => 'Language(s)', 'deadline' => 'Deadline', 'message' => 'Message', 'question' => 'Question'],
        'services' => ['translation' => 'Translation', 'proofreading' => 'Proofreading', 'post_editing' => 'Post-editing (PEMT)', 'interpreting' => 'Interpreting', 'spelling' => 'Spelling advice'],
        'interpreting' => ['consecutive' => 'Consecutive interpreting', 'simultaneous' => 'Simultaneous interpreting', 'remote' => 'Remote interpreting', 'chuchotage' => 'Chuchotage', 'other' => 'Other'],
        'deadlines' => ['normal' => 'Standard (2–5 working days)', 'urgent' => 'Urgent (1–2 working days)', 'express' => 'Express (even on the same day)'],
    ];

    private const HU_LABELS = ['service' => 'Szolgáltatás', 'languages' => 'Nyelv(ek)', 'deadline' => 'Határidő', 'message' => 'Üzenet', 'question' => 'Kérdés'];

    /**
     * Amit az ügyfél beküldött, az ő nyelvén (a dokumentumot külön mondat igazolja vissza): így a levélből is látja, mire vár választ.
     *
     * @return array<string, string> címke => érték (csak a kitöltött mezők)
     */
    public function summary(): array
    {
        $q = $this->quote;
        $en = $q->locale === 'en';
        $l = $en ? self::EN['labels'] : self::HU_LABELS;

        $service = $en ? (self::EN['services'][$q->service] ?? $q->service) : (QuoteRequest::SERVICES[$q->service] ?? $q->service);
        if ($q->service === 'interpreting' && $q->interpreting_type) {
            $service .= ' – '.($en ? (self::EN['interpreting'][$q->interpreting_type] ?? '') : (QuoteRequest::INTERPRETING_TYPES[$q->interpreting_type] ?? ''));
        }

        $languages = collect([$q->source_language, $q->target_language])->filter(fn ($v) => filled($v))->implode(' → ');
        $deadline = $q->service === 'spelling' ? null
            : ($en ? (self::EN['deadlines'][$q->deadline] ?? null) : (QuoteRequest::DEADLINES[$q->deadline] ?? null));
        $message = filled($q->message) ? Str::limit(trim($q->message), 400) : null;

        return array_filter([
            $l['service'] => rtrim($service, ' –'),
            $l['languages'] => $languages ?: null,
            $l['deadline'] => $deadline,
            $l[$q->service === 'spelling' ? 'question' : 'message'] => $message,
        ], fn ($v) => filled($v));
    }
}

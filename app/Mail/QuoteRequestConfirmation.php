<?php

namespace App\Mail;

use App\Models\QuoteRequest;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

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
        return new Content(markdown: $this->quote->locale === 'en'
            ? 'mail.quote-request-confirmation-en'
            : 'mail.quote-request-confirmation');
    }
}

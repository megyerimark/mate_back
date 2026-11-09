<?php

namespace App\Support;

use App\Mail\QuoteRequestConfirmation;
use App\Mail\QuoteRequestReceived;
use App\Models\QuoteRequest;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Str;
use Throwable;

/**
 * Az ajánlatkérés két levele: értesítő az irodának (a csatolt dokumentummal) és visszaigazolás az ügyfélnek.
 * A kettő külön megy ki, hogy az egyik hibája ne akassza meg a másikat; az eredmény az ajánlatkérésen marad,
 * így az admin felület mutatja, ha valami nem ment ki, és onnan újraküldhető.
 */
class QuoteMailer
{
    /** @param  list<'notify'|'confirm'>  $which */
    public function send(QuoteRequest $quote, array $which = ['notify', 'confirm']): void
    {
        $errors = [];

        if (in_array('notify', $which, true)) {
            $errors[] = $this->attempt($quote, 'notified_at', fn () => Mail::to(config('quotes.recipient'))->send(new QuoteRequestReceived($quote)));
        }
        if (in_array('confirm', $which, true)) {
            $errors[] = $this->attempt($quote, 'confirmed_at', fn () => Mail::to($quote->email)->send(new QuoteRequestConfirmation($quote)));
        }

        $quote->mail_error = Str::limit(implode(' | ', array_filter($errors)), 490) ?: null;
        $quote->save();
    }

    private function attempt(QuoteRequest $quote, string $column, callable $send): ?string
    {
        try {
            $send();
            $quote->{$column} = now();

            return null;
        } catch (Throwable $e) {
            Log::error('Az ajánlatkérés e-mailje nem ment ki', ['quote_request_id' => $quote->id, 'mail' => $column, 'error' => $e->getMessage()]);

            return $e->getMessage();
        }
    }
}

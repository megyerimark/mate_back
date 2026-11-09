<?php

namespace App\Rules;

use Closure;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

/**
 * Google reCAPTCHA v3 ellenőrzés. Csak akkor fut, ha be van állítva a titkos kulcs (RECAPTCHA_SECRET_KEY).
 * A v3 nem kér semmit a látogatótól: a Google pontszámot ad (0 = bot, 1 = ember).
 */
class Recaptcha implements ValidationRule
{
    public function __construct(private readonly string $action, private readonly string $message) {}

    public static function enabled(): bool
    {
        return filled(config('services.recaptcha.secret'));
    }

    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        try {
            $result = Http::asForm()->timeout(5)->post('https://www.google.com/recaptcha/api/siteverify', [
                'secret' => config('services.recaptcha.secret'),
                'response' => $value,
                'remoteip' => request()->ip(),
            ])->json();
        } catch (ConnectionException $e) {
            // Ha a Google nem érhető el, ne veszítsük el miatta az ügyfelet; a honeypot és a rate limit továbbra is véd.
            Log::warning('A reCAPTCHA ellenőrzés nem érhető el', ['error' => $e->getMessage()]);

            return;
        }

        $ok = ($result['success'] ?? false)
            && ($result['action'] ?? null) === $this->action
            && (float) ($result['score'] ?? 0) >= (float) config('services.recaptcha.min_score', 0.5);

        if (! $ok) {
            Log::info('reCAPTCHA elutasítva', ['score' => $result['score'] ?? null, 'errors' => $result['error-codes'] ?? null]);
            $fail($this->message);
        }
    }
}

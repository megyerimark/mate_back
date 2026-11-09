<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreQuoteRequest;
use App\Models\QuoteRequest;
use App\Support\QuoteMailer;
use Illuminate\Http\JsonResponse;

class QuoteRequestController extends Controller
{
    public function store(StoreQuoteRequest $request, QuoteMailer $mailer): JsonResponse
    {
        $ok = response()->json(['message' => 'Köszönjük, megkaptuk az ajánlatkérést!'], 201);

        // Botnak ugyanazt a választ adjuk, de semmit nem mentünk.
        if ($request->isSpam()) {
            return $ok;
        }

        $data = $request->safe()->except(['document', 'documents', 'privacy_accepted', 'website', 'recaptcha_token']);
        $data['service'] = $data['service'] ?? 'translation';
        $data['locale'] = $data['locale'] ?? 'hu';
        // A tolmácsolás típusa csak tolmácsolásnál értelmes.
        if ($data['service'] !== 'interpreting') {
            $data['interpreting_type'] = null;
        }

        $quote = QuoteRequest::create($data + ['ip_address' => $request->ip()]);

        foreach ($request->uploadedFiles() as $file) {
            $quote->documents()->create([
                'path' => $file->store('quote-requests', 'local'),
                'name' => $file->getClientOriginalName(),
                'size' => $file->getSize(),
                'mime' => $file->getClientMimeType(),
            ]);
        }

        // Az ajánlatkérés el van mentve; ha a levélküldés hibázik, ne veszítsük el miatta az ügyfelet
        // (az admin felület jelzi, és onnan újraküldhető).
        $mailer->send($quote);

        return $ok;
    }
}

<?php

namespace App\Http\Requests;

use App\Models\QuoteRequest;
use App\Rules\Recaptcha;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreQuoteRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:120'],
            'email' => ['required', 'email', 'max:190'],
            'phone' => ['nullable', 'string', 'max:40'],
            'service' => ['nullable', Rule::in(array_keys(QuoteRequest::SERVICES))],
            'interpreting_type' => ['nullable', Rule::in(array_keys(QuoteRequest::INTERPRETING_TYPES))],
            'locale' => ['nullable', Rule::in(QuoteRequest::LOCALES)],
            'deadline' => ['required', Rule::in(array_keys(QuoteRequest::DEADLINES))],
            'source_language' => ['nullable', 'string', 'max:50'],
            'target_language' => ['nullable', 'string', 'max:100'],
            // Helyesírási tanácsadásnál a kérdés maga az üzenet.
            'message' => ['nullable', 'required_if:service,spelling', 'string', 'max:5000'],
            // Egy fájl (régi űrlap) vagy több fájl (documents[]), legfeljebb 5, összesen max_total_kb.
            'document' => ['nullable', ...self::fileRules()],
            'documents' => ['nullable', 'array', 'max:'.self::MAX_FILES],
            'documents.*' => self::fileRules(),
            'privacy_accepted' => ['accepted'],
            // Honeypot: emberek nem látják, botok kitöltik.
            'website' => ['nullable', 'string'],
            'recaptcha_token' => Recaptcha::enabled()
                ? ['required', 'string', new Recaptcha('quote', $this->recaptchaMessage())]
                : ['nullable'],
        ];
    }

    private function recaptchaMessage(): string
    {
        return $this->input('locale') === 'en'
            ? 'The security check failed. Please reload the page and try again, or send us an email.'
            : 'A biztonsági ellenőrzés nem sikerült. Kérjük, töltse újra az oldalt és próbálja újra, vagy írjon e-mailt.';
    }

    public const MAX_FILES = 5;

    /** @return list<string> */
    private static function fileRules(): array
    {
        return ['file', 'max:'.config('quotes.max_file_kb'), 'mimes:pdf,doc,docx,jpg,jpeg,png,txt,xlsx,pptx'];
    }

    /** @return list<\Illuminate\Http\UploadedFile> */
    public function uploadedFiles(): array
    {
        return array_values(array_filter([...(array) $this->file('documents', []), $this->file('document')]));
    }

    public function after(): array
    {
        return [function ($validator) {
            $totalKb = array_sum(array_map(fn ($f) => $f->getSize(), $this->uploadedFiles())) / 1024;
            if ($totalKb > config('quotes.max_total_kb')) {
                $mb = intdiv(config('quotes.max_total_kb'), 1024);
                $validator->errors()->add('documents', $this->input('locale') === 'en'
                    ? "The files together may be at most {$mb} MB. Please send the rest by email."
                    : "A fájlok együtt legfeljebb {$mb} MB-osak lehetnek. A többit kérjük, küldje e-mailben.");
            }
        }];
    }

    public function messages(): array
    {
        $mb = intdiv(config('quotes.max_file_kb'), 1024);

        if ($this->input('locale') === 'en') {
            return [
                'name.required' => 'Please enter your name.',
                'email.required' => 'Please enter your email address.',
                'email.email' => 'Please enter a valid email address.',
                'deadline.required' => 'Please choose a deadline.',
                'deadline.in' => 'Invalid deadline.',
                'service.in' => 'Invalid service.',
                'interpreting_type.in' => 'Invalid interpreting type.',
                'message.required_if' => 'Please write your question.',
                'document.max' => "The file is too large (max. {$mb} MB). Please send it by email.",
                'document.mimes' => 'This file type is not supported. You can upload PDF, Word, Excel, PowerPoint, image or text files.',
                'document.uploaded' => 'The file could not be uploaded. Please try again or send it by email.',
                'document.file' => 'The document could not be uploaded.',
                'documents.max' => 'You can upload at most '.self::MAX_FILES.' files. Please send the rest by email.',
                'documents.*.max' => "A file is too large (max. {$mb} MB). Please send it by email.",
                'documents.*.mimes' => 'This file type is not supported. You can upload PDF, Word, Excel, PowerPoint, image or text files.',
                'documents.*.uploaded' => 'A file could not be uploaded. Please try again or send it by email.',
                'documents.*.file' => 'A file could not be uploaded.',
                'privacy_accepted.accepted' => 'You need to accept the Privacy Policy.',
                'recaptcha_token.required' => $this->recaptchaMessage(),
                '*.max' => 'The :attribute may be at most :max characters.',
                '*.string' => 'The :attribute field is invalid.',
            ];
        }

        return [
            'name.required' => 'Kérjük, adja meg a nevét.',
            'email.required' => 'Kérjük, adja meg az e-mail-címét.',
            'email.email' => 'Kérjük, adjon meg egy érvényes e-mail-címet.',
            'deadline.required' => 'Kérjük, válasszon határidőt.',
            'deadline.in' => 'Érvénytelen határidő.',
            'service.in' => 'Ismeretlen szolgáltatás.',
            'interpreting_type.in' => 'Ismeretlen tolmácsolási típus.',
            'message.required_if' => 'Kérjük, írja meg a kérdését.',
            'document.max' => "A fájl túl nagy (max. {$mb} MB). Kérjük, küldje e-mailben.",
            'document.mimes' => 'Ez a fájltípus nem támogatott. PDF, Word, Excel, PowerPoint, kép vagy szöveg tölthető fel.',
            'document.uploaded' => 'A fájl feltöltése nem sikerült. Kérjük, próbálja újra vagy küldje e-mailben.',
            'document.file' => 'A dokumentum feltöltése nem sikerült.',
            'documents.max' => 'Legfeljebb '.self::MAX_FILES.' fájl tölthető fel. A többit kérjük, küldje e-mailben.',
            'documents.*.max' => "Egy fájl túl nagy (max. {$mb} MB). Kérjük, küldje e-mailben.",
            'documents.*.mimes' => 'Ez a fájltípus nem támogatott. PDF, Word, Excel, PowerPoint, kép vagy szöveg tölthető fel.',
            'documents.*.uploaded' => 'Egy fájl feltöltése nem sikerült. Kérjük, próbálja újra vagy küldje e-mailben.',
            'documents.*.file' => 'Egy fájl feltöltése nem sikerült.',
            'privacy_accepted.accepted' => 'Az adatvédelmi nyilatkozat elfogadása kötelező.',
            'recaptcha_token.required' => $this->recaptchaMessage(),
            '*.max' => 'A(z) :attribute legfeljebb :max karakter lehet.',
            '*.string' => 'A(z) :attribute mező formátuma érvénytelen.',
        ];
    }

    public function attributes(): array
    {
        return $this->input('locale') === 'en'
            ? ['name' => 'name', 'email' => 'email', 'phone' => 'phone', 'source_language' => 'source language', 'target_language' => 'target language', 'message' => 'message']
            : ['name' => 'név', 'email' => 'e-mail', 'phone' => 'telefon', 'source_language' => 'forrásnyelv', 'target_language' => 'célnyelv', 'message' => 'üzenet'];
    }

    public function isSpam(): bool
    {
        return filled($this->input('website'));
    }
}

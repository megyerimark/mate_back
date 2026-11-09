<?php

namespace App\Http\Requests\Admin;

use App\Models\QuoteRequest;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateQuoteRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'status' => ['sometimes', 'required', Rule::in(array_keys(QuoteRequest::STATUSES))],
            'admin_note' => ['sometimes', 'nullable', 'string', 'max:5000'],
            'price' => ['sometimes', 'nullable', 'integer', 'min:0', 'max:100000000'],
        ];
    }

    public function messages(): array
    {
        return [
            'status.in' => 'Ismeretlen állapot.',
            'admin_note.max' => 'A jegyzet legfeljebb 5000 karakter lehet.',
            'price.integer' => 'Az ár egész szám legyen (forintban).',
            'price.min' => 'Az ár nem lehet negatív.',
            'price.max' => 'Ez az ár túl nagy.',
        ];
    }
}

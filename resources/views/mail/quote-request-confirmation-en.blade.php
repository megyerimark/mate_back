<x-mail::message>
# Dear {{ $quote->name }},

Thank you, we have received your message. Our project managers will contact you as soon as possible{{ $quote->service !== 'spelling' ? ' and prepare a custom quote for you' : '' }}.

@if ($files = collect($quote->files())->pluck('name')->join(', '))
{{ count($quote->files()) > 1 ? 'Your attached documents' : 'Your attached document' }} ({{ $files }}) {{ count($quote->files()) > 1 ? 'have' : 'has' }} arrived safely.
@endif

<x-mail::panel>
@foreach ($summary as $label => $value)
<p class="summary-row"><span class="summary-label">{{ $label }}:</span> {!! nl2br(e($value)) !!}</p>
@endforeach
</x-mail::panel>

We work to premium standards: at least two professional translators work on every translation, and we guarantee our deadlines. With us, you don’t get 100% – you get 110%.

If it is urgent or you have any questions, reply to this email or call us: {{ config('quotes.phone') }}

Kind regards,<br>
{{ config('app.name') }}
</x-mail::message>

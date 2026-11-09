<x-mail::message>
# Dear {{ $quote->name }},

Thank you, we have received your message. Our project managers will contact you as soon as possible@if ($quote->service !== 'spelling') and prepare a custom quote for you@endif.

@if ($quote->document_name)
Your attached document ({{ $quote->document_name }}) has arrived safely.
@endif

If it is urgent or you have any questions, reply to this email or call us: {{ config('quotes.phone') }}

Kind regards,<br>
{{ config('app.name') }}
</x-mail::message>

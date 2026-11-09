<x-mail::message>
# Kedves {{ $quote->name }}!

Köszönjük, megkaptuk az üzenetét. A projektmenedzsereink a lehető leghamarabb felveszik Önnel a kapcsolatot{{ $quote->service !== 'spelling' ? ', és egyedi árajánlatot készítenek Önnek' : '' }}.

@if ($quote->document_name)
A csatolt dokumentum ({{ $quote->document_name }}) rendben megérkezett.
@endif

<x-mail::panel>
@foreach ($summary as $label => $value)
<p class="summary-row"><span class="summary-label">{{ $label }}:</span> {!! nl2br(e($value)) !!}</p>
@endforeach
</x-mail::panel>

Ha sürgős, vagy kérdése van, válaszoljon erre a levélre, vagy hívjon minket: {{ config('quotes.phone') }}

Üdvözlettel,<br>
{{ config('app.name') }}
</x-mail::message>

<x-mail::message>
# Kedves {{ $quote->name }}!

Köszönjük, megkaptuk az üzenetét. A projektmenedzsereink a lehető leghamarabb felveszik Önnel a kapcsolatot@if ($quote->service !== 'spelling'), és egyedi árajánlatot készítenek Önnek@endif.

@if ($quote->document_name)
A csatolt dokumentum ({{ $quote->document_name }}) rendben megérkezett.
@endif

Ha sürgős, vagy kérdése van, válaszoljon erre a levélre, vagy hívjon minket: {{ config('quotes.phone') }}

Üdvözlettel,<br>
{{ config('app.name') }}
</x-mail::message>

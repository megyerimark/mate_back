<x-mail::message>
# Kedves {{ $quote->name }}!

Köszönjük, megkaptuk az üzenetét. A projektmenedzsereink a lehető leghamarabb felveszik Önnel a kapcsolatot{{ $quote->service !== 'spelling' ? ', és egyedi árajánlatot készítenek Önnek' : '' }}.

@if ($files = collect($quote->files())->pluck('name')->join(', '))
{{ count($quote->files()) > 1 ? 'A csatolt dokumentumok' : 'A csatolt dokumentum' }} ({{ $files }}) rendben megérkezett.
@endif

<x-mail::panel>
@foreach ($summary as $label => $value)
<p class="summary-row"><span class="summary-label">{{ $label }}:</span> {!! nl2br(e($value)) !!}</p>
@endforeach
</x-mail::panel>

Prémium minőségben dolgozunk: minden fordításon legalább két szakfordító dolgozik, és a határidőre is garanciát vállalunk. Nálunk nem 100%-ot kap, hanem 110%-ot.

Ha sürgős, vagy kérdése van, válaszoljon erre a levélre, vagy hívjon minket: {{ config('quotes.phone') }}

Üdvözlettel,<br>
{{ config('app.name') }}
</x-mail::message>

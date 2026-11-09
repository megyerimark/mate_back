<x-mail::layout>
{{-- Fejléc: zöld pipa és a cég neve --}}
<x-slot:header>
<x-mail::header :url="config('app.url')">
{{ config('app.name') }}
</x-mail::header>
</x-slot:header>

{{-- Tartalom --}}
{!! $slot !!}

{{-- Kiegészítő szöveg --}}
@isset($subcopy)
<x-slot:subcopy>
<x-mail::subcopy>
{!! $subcopy !!}
</x-mail::subcopy>
</x-slot:subcopy>
@endisset

{{-- Lábléc --}}
<x-slot:footer>
<x-mail::footer>
<p class="tagline">{{ app()->getLocale() === 'en' ? 'Premium translation · 110%' : 'Prémium fordítás · 110%' }}</p>

© {{ date('Y') }} {{ config('app.name') }} · {{ preg_replace('#^https?://#', '', rtrim(config('app.url'), '/')) }}
</x-mail::footer>
</x-slot:footer>
</x-mail::layout>

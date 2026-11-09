@props(['url'])
<tr>
<td class="header">
<a href="{{ $url }}" style="display: inline-block;">
{{-- A logó tartalmazza a cég nevét; ha a levelezőprogram nem tölti be a képet, az alt szöveg látszik helyette. --}}
<img src="{{ asset('email/done-translations-logo-light.png') }}" class="brand-logo" width="180" height="67" alt="{{ trim(strip_tags($slot)) }}">
</a>
</td>
</tr>

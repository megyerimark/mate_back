<x-mail::message>
# Új ajánlatkérés érkezett

<x-mail::table>
| | |
|:--|:--|
| **Név** | {{ $quote->name }} |
| **E-mail** | {{ $quote->email }} |
| **Telefon** | {{ $quote->phone ?: '–' }} |
| **Szolgáltatás** | {{ $quote->serviceLabel() }} |
| **Határidő** | {{ $quote->deadlineLabel() }} |
| **Nyelv(ek)** | {{ $quote->source_language ? $quote->source_language.' → ' : '' }}{{ $quote->target_language ?: '–' }} |
| **Weboldal nyelve** | {{ $quote->locale === 'en' ? 'angol (a visszaigazolást angolul kapta)' : 'magyar' }} |
| **Dokumentum** | {{ $quote->document_name ?: 'nincs csatolva' }} |
</x-mail::table>

@if ($quote->message)
**Megjegyzés:**

{{ $quote->message }}
@endif

Erre a levélre válaszolva közvetlenül az ügyfélnek ír. Ne felejtse: 1 órán belül válaszolni!

Azonosító: #{{ $quote->id }}
</x-mail::message>

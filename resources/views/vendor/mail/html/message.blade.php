@props(['preheader' => null, 'bannerUrl' => null, 'bannerAlt' => null])
<x-mail::layout :preheader="$preheader" :banner-url="$bannerUrl" :banner-alt="$bannerAlt">
{{-- Header --}}
<x-slot:header>
<x-mail::header :url="config('app.url')">
{{ config('lri.site.name') }}
</x-mail::header>
</x-slot:header>

{{-- Body --}}
{!! $slot !!}

{{-- Subcopy --}}
@isset($subcopy)
<x-slot:subcopy>
<x-mail::subcopy>
{!! $subcopy !!}
</x-mail::subcopy>
</x-slot:subcopy>
@endisset

{{-- Footer --}}
<x-slot:footer>
<x-mail::footer>
**{{ config('lri.site.slogan') }}**

[Instagram]({{ config('lri.links.instagram') }}) · [TikTok]({{ config('lri.links.tiktok') }}) · [Strava]({{ config('lri.links.strava') }})

{{ config('lri.site.name') }}, {{ config('lri.site.locality') }}, {{ config('lri.site.region') }}
</x-mail::footer>
</x-slot:footer>
</x-mail::layout>

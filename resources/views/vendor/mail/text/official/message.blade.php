@props(['preheader' => null])
{{ config('lri.site.name') }}

{{ $slot }}

{{ config('lri.site.name') }}, {{ config('lri.site.locality') }}, {{ config('lri.site.region') }}
{{ config('app.url') }}

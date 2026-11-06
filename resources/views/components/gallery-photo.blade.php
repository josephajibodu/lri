@props(['photo', 'sizes'])

@php
    $smallUrl = \App\Support\SiteAsset::url("gallery/{$photo['name']}-thumb.webp");
    $largeUrl = \App\Support\SiteAsset::url("gallery/{$photo['name']}-large.webp");
@endphp

<img {{ $attributes }} src="{{ $smallUrl }}" srcset="{{ $smallUrl }} {{ min(640, $photo['width']) }}w, {{ $largeUrl }} {{ $photo['width'] }}w" sizes="{{ $sizes }}" width="{{ $photo['width'] }}" height="{{ $photo['height'] }}" alt="{{ $photo['alt'] }}" loading="lazy" decoding="async">

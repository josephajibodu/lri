@props(['person', 'alt' => ''])

<div {{ $attributes->merge(['class' => 'fd-portrait fd-tone-'.$person['tone']]) }}>
    @if ($person['photo'])
        <img src="{{ asset($person['photo']) }}" alt="{{ $alt }}" loading="lazy" decoding="async">
    @else
        <span class="fd-initials" aria-hidden="true">{{ $person['initials'] }}</span>
        <span class="fd-initials-tag" aria-hidden="true">LRI · No. {{ $person['bib'] }}</span>
    @endif
</div>

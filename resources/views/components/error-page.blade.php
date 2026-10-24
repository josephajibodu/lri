@props([
    'code',
    'label',
    'title',
    'highlight',
    'message',
])

<x-layouts.app :title="$label.' · Let’s Run Ibadan'" robots="noindex">
    <x-site.header />

    <main class="error-page">
        <div class="wrap error-inner">
            <div class="bib-card error-bib">
                <span class="pin tl" aria-hidden="true"></span>
                <span class="pin tr" aria-hidden="true"></span>
                <span class="pin bl" aria-hidden="true"></span>
                <span class="pin br" aria-hidden="true"></span>
                <div class="bib-strip"><span>Let’s Run Ibadan</span><span>Error</span></div>
                <p class="error-number">{{ $code }}</p>
                <p class="error-label">{{ $label }}</p>
            </div>
            <h1 class="error-title">{{ $title }} <span class="wm-hl">{{ $highlight }}</span></h1>
            <p class="lede">{{ $message }}</p>
            <div class="cta-row">
                <a class="btn btn-yellow" href="{{ route('home') }}">Back to the start</a>
                <a class="btn" href="{{ config('lri.links.instagram') }}" target="_blank" rel="noopener">Follow @@letsrunibadan</a>
            </div>
        </div>
    </main>

    <x-site.footer />
</x-layouts.app>

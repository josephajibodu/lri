@php($shareText = 'Silent Disco Run with Let’s Run Ibadan'.($startsAt ? ', '.$startsAt->format('D j M, g A') : '').($discoRun['venue'] ? ' in '.$discoRun['venue'] : '').'. Headsets on, run and vibe, then rave. Get your ticket: '.route('disco-run'))
<section class="dr-sec dr-share" id="share">
    <div class="wrap dr-share-grid">
        <figure class="dr-poster" data-reveal>
            <span class="dr-poster-tape" aria-hidden="true"></span>
            <img src="{{ asset($discoRun['poster']) }}" width="800" height="1000" alt="The Silent Disco Run flyer: runners in glowing headphones under the words Silent Disco Run.{{ $startsAt ? ' '.$startsAt->format('l j F, g A').',' : '' }} {{ $discoRun['venue'] ?? 'Ibadan' }}.{{ $discoRun['price'] ? ' Tickets '.$discoRun['price'].', '.Str::lower($discoRun['price_note'] ?? '').'.' : '' }}" loading="lazy" decoding="async">
        </figure>

        <div class="dr-share-copy" data-reveal style="--d: .1s">
            <p class="dr-label">Spread the word</p>
            <h2>Bring your <em>crew.</em></h2>
            <p class="dr-sub">Silent discos are better loud. Send the flyer to the group chat, post it on your status, and tag <strong>@@letsrunibadan</strong> so we can share it on.</p>
            <p class="dr-share-tagline">One night. One vibe. Endless memories.</p>
            <div class="cta-row dr-share-actions">
                <a class="btn dr-btn" href="https://wa.me/?text={{ rawurlencode($shareText) }}" target="_blank" rel="noopener">Share on WhatsApp ↗</a>
                <a class="btn dr-btn-ghost" href="{{ asset('images/disco-run/flyer-full.jpg') }}" download="lri-silent-disco-run-flyer.jpg">Download the flyer ↓</a>
            </div>
        </div>
    </div>
</section>

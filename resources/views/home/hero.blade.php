<section class="hero" id="top" data-hero-scene>
    <div class="hero-sky" aria-hidden="true">
        <span class="hero-rays"></span>
        <span class="hero-sun" data-hero-sun></span>
    </div>
    <canvas class="hero-canvas" data-hero-back aria-hidden="true"></canvas>

    <div class="wrap hero-title">
        <h1 class="wordmark"><span class="wm-line">Let’s run,</span><span class="wm-line"><span class="wm-hl">Ibadan.</span></span></h1>
    </div>

    <canvas class="hero-canvas hero-canvas-front" data-hero-front aria-hidden="true"></canvas>

    <div class="wrap hero-intro">
        <p class="lede">Runners, joggers and walkers in local tribes across Ibadan. We meet every Saturday at 6:30 AM, run 5 km or more together, and nobody gets left behind.</p>
        <div class="hero-actions">
            <ul class="hero-facts">
                <li>Everyone</li>
                <li>Every pace</li>
                <li>One community</li>
            </ul>
            <div class="cta-row">
                <a class="btn btn-yellow" href="{{ config('lri.links.member_form') }}" target="_blank" rel="noopener">Become a member ↗</a>
                <a class="btn" href="{{ config('lri.links.instagram') }}" target="_blank" rel="noopener">Follow @@letsrunibadan</a>
            </div>
        </div>
    </div>

    <div class="hero-road" data-hero-road></div>

    <div class="hero-hud" data-hero-hud hidden>
        <div class="wrap hero-hud-row">
            <button class="hero-pause" type="button" data-hero-pause aria-pressed="false" aria-label="Pause the runners"></button>
            <p class="hero-pace" aria-hidden="true">Pace <b data-hero-pace>7:00</b> /km <span data-hero-band>Easy run</span></p>
            <p class="hero-hint" aria-hidden="true"><span class="hero-hint-fine">Move across the page to set the pace</span><span class="hero-hint-touch">Drag to set the pace</span></p>
        </div>
    </div>

    <div class="stickers" aria-hidden="true">
        <div class="sticker s-badge">
            <svg viewBox="0 0 136 136">
                <defs><path id="badge-circle" d="M68 68 m-49 0 a49 49 0 1 1 98 0 a49 49 0 1 1 -98 0"></path></defs>
                <circle class="badge-disc" cx="68" cy="68" r="67"></circle>
                <g class="badge-spin"><text class="badge-ring"><textPath href="#badge-circle" textLength="304" lengthAdjust="spacing">Everyone · Every pace · One community ·</textPath></text></g>
                <text class="badge-core" x="68" y="78" text-anchor="middle">LRI</text>
            </svg>
        </div>
        <div class="sticker s-burst"><span><b>6:30</b><small>AM · Sat</small></span></div>
        <div class="sticker s-pill">Walking allowed</div>
        <div class="sticker s-km"><span>5+<small>km</small></span></div>
        <div class="sticker s-tag">No one left behind</div>
        <p class="drag-hint">Psst. The stickers move.</p>
    </div>
</section>

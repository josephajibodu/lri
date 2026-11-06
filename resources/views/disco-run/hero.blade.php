<section class="dr-hero" id="top">
    <div class="dr-beams" aria-hidden="true"><span></span><span></span><span></span></div>
    <div class="dr-spot" aria-hidden="true"></div>

    <img class="dr-headphone dr-headphone-l" src="{{ \App\Support\SiteAsset::url('images/disco-run/headphone.png') }}" width="216" height="216" alt="" aria-hidden="true">
    <img class="dr-headphone dr-headphone-r" src="{{ \App\Support\SiteAsset::url('images/disco-run/headphone.png') }}" width="216" height="216" alt="" aria-hidden="true">

    <div class="wrap dr-hero-inner">
        <p class="dr-kicker"><span class="dr-live"></span>Let’s Run Ibadan presents</p>

        <h1 class="dr-title" aria-label="{{ $discoRun['title'] }}">
            <span class="dr-title-line dr-outline" aria-hidden="true">Silent</span>
            <span class="dr-title-line dr-beat" aria-hidden="true">
                @foreach (str_split('DISCO') as $letter)
                    <span style="--i: {{ $loop->index }}">{{ $letter }}</span>
                @endforeach
            </span>
            <span class="dr-title-line" aria-hidden="true">Run<span class="dr-dot">.</span></span>
        </h1>

        <div class="dr-eq" aria-hidden="true">
            @for ($bar = 0; $bar < 24; $bar++)
                <span style="--b: {{ $bar }}; --h: {{ [.35, .8, .55, 1, .45, .7, .9, .4][$bar % 8] }}"></span>
            @endfor
        </div>

        <p class="dr-lede">Headsets on. The city hears nothing, you hear everything. Run and vibe through Ibadan at night on three live DJ channels, then we rave at the finish.</p>

        <ul class="dr-facts">
            <li>{{ $when }}</li>
            <li>{{ $discoRun['distance'] }}</li>
            <li>{{ $discoRun['venue'] ?? 'Ibadan' }}</li>
        </ul>

        <div class="cta-row dr-buy dr-tone-{{ $tone }}">
            <a class="btn dr-btn" href="#tickets">Get your ticket ↓</a>
            <a class="btn dr-btn-ghost" href="#how">How it works</a>
        </div>
    </div>

    <figure class="dr-hero-crowd">
        <img src="{{ \App\Support\SiteAsset::url('images/disco-run/crowd.jpg') }}" width="1080" height="305" alt="Runners laughing and running together, each wearing a glowing silent disco headset in green, blue, red or pink." decoding="async">
    </figure>
</section>

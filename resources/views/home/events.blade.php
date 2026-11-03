<section class="sec sec-paper" id="events">
    <div class="wrap">
        <div class="sec-head">
            <p class="label">2026 so far</p>
            <h2>We run for something</h2>
            <p class="sec-lede">Some Saturdays are bigger than a run. We use our kilometres to raise awareness, back good causes and bring the city out together.</p>
        </div>
        @if ($discoRun)
            <a class="up-next" href="{{ route('disco-run') }}">
                <img class="up-next-poster" src="{{ asset($discoRun['poster']) }}" width="800" height="1000" alt="" loading="lazy" decoding="async">
                <span class="up-next-body">
                    <span class="up-next-label"><span class="up-next-dot" aria-hidden="true"></span>Up next</span>
                    <span class="up-next-title">Silent Disco Run</span>
                    <span class="up-next-meta">
                        @if ($discoRun['starts_at'])
                            {{ $discoRun['starts_at']->format('D j M · g A') }}
                        @else
                            {{ $discoRun['date_window'] }}
                        @endif
                        @if ($discoRun['venue']) · {{ $discoRun['venue'] }}@endif
                        @if ($discoRun['price']) · {{ $discoRun['price'] }}@endif
                    </span>
                    <span class="up-next-text">Headsets on, three live DJ channels, a night run through the city, then a rave at the finish.</span>
                </span>
                <span class="up-next-go">See the night →</span>
            </a>
        @endif
        <div class="events">
            <article class="tile tile-green tile-big">
                <div class="tile-top"><span class="tile-date">10–15 Aug</span><span class="tile-tag">Ibadan host partner</span></div>
                <h3>Run4Breath</h3>
                <p>Six days to log 21 km, then a city run on 15 August in a touch of green. Every registration helped provide inhalers for people living with asthma.</p>
                <p class="tile-stat">21 km<small>in 6 days</small></p>
            </article>
            <article class="tile tile-sky tile-wide">
                <div class="tile-top"><span class="tile-date">26 Sep</span><span class="tile-tag">Official Ibadan host</span></div>
                <h3>Move Week Africa</h3>
                <p>Run &amp; Rave, a flag football scrimmage and a prostate cancer awareness talk at The Law Hub.</p>
            </article>
            <article class="tile tile-pink tile-wide">
                <div class="tile-top"><span class="tile-date">31 Oct</span><span class="tile-tag">All tribes</span></div>
                <h3>Breast Cancer Awareness Run</h3>
                <p>Every tribe at The Law Hub to close out the month, then the community hangout.</p>
            </article>
            <article class="tile">
                <div class="tile-top"><span class="tile-date">4 Jul</span></div>
                <h3>Run, Padel &amp; Chill</h3>
                <p>A community run, then the padel courts at a discounted LRI rate.</p>
            </article>
            <article class="tile tile-cream">
                <div class="tile-top"><span class="tile-date">25 Jul</span></div>
                <h3>The women of LRI</h3>
                <p>Interviews, photos and a group shot for the International Day for Women and Girls of African Descent.</p>
            </article>
            <article class="tile tile-yellow">
                <div class="tile-top"><span class="tile-date">1 Aug</span></div>
                <h3>The Ring Road run</h3>
                <p>All tribes from The Law Hub through Molete, Dugbe and Eleyele.</p>
            </article>
            <article class="tile">
                <div class="tile-top"><span class="tile-date">5 Sep</span></div>
                <h3>Bodija hangout</h3>
                <p>5K and 10K loops from Osuntokun, then fruit, water and gist.</p>
            </article>
        </div>
        <p class="next-note">In the works: {{ $discoRun ? 'the end-of-year party. The date goes' : 'a silent disco and the end-of-year party. Dates go' }} out on <a href="{{ config('lri.links.instagram') }}" target="_blank" rel="noopener">@@letsrunibadan</a>.</p>
    </div>
</section>

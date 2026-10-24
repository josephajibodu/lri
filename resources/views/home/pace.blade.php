<section class="sec sec-paper" id="pace">
    <div class="wrap pace-grid">
        <div class="sec-head">
            <p class="label">Every pace</p>
            <h2>Pick a pace. <em>Any pace.</em></h2>
            <p class="sec-lede">Slide to the pace you usually run or walk. Fast, slow or somewhere in between, someone in LRI is moving at it on Saturday.</p>
        </div>
        <div class="pace-card">
            <div class="pace-readout">
                <span class="pace-big" id="pace-out">7:00</span>
                <span class="pace-unit">min / km</span>
                <span class="pace-label" id="pace-label">Easy run</span>
            </div>
            <label class="sr-only" for="pace-input">Your pace in minutes per kilometre</label>
            <input class="range" type="range" id="pace-input" min="210" max="780" step="15" value="420">
            <div class="pace-scale" aria-hidden="true"><span>3:30 Faster</span><span>Slower 13:00</span></div>
            <dl class="pace-times">
                <div><dt>Your 5 km</dt><dd id="t5">35:00</dd></div>
                <div><dt>Your 10 km</dt><dd id="t10">1:10:00</dd></div>
            </dl>
            <p class="pace-msg" id="pace-msg">The sweet spot for most Saturday groups.</p>
            <div class="track" aria-hidden="true">
                <div class="lane lane-you"><span class="lane-name">You</span><span class="runner" id="you-runner" style="--dur:8.4s;--rest:44%"></span></div>
                <div class="lane"><span class="lane-name">5:30</span><span class="runner" style="--dur:6.6s;--delay:-2.4s;--rest:62%"></span></div>
                <div class="lane"><span class="lane-name">8:00</span><span class="runner" style="--dur:9.6s;--delay:-5.1s;--rest:36%"></span></div>
                <div class="lane"><span class="lane-name">11:00</span><span class="runner" style="--dur:13.2s;--delay:-3.3s;--rest:18%"></span></div>
            </div>
        </div>
    </div>
</section>

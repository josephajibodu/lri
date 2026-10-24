<section class="next" id="next" aria-labelledby="next-title">
    <div class="wrap">
        <div class="bib-card">
            <span class="pin tl" aria-hidden="true"></span>
            <span class="pin tr" aria-hidden="true"></span>
            <span class="pin bl" aria-hidden="true"></span>
            <span class="pin br" aria-hidden="true"></span>
            <div class="bib-strip"><span>Let’s Run Ibadan</span><span id="next-status">Next community run</span></div>
            <div class="bib-body">
                <div class="next-info">
                    <h2 id="next-title">{{ $nextRun['title'] }}</h2>
                    <ul class="next-meta">
                        <li>{{ $nextRun['starts_at']->format('D j M Y') }}</li>
                        <li>{{ $nextRun['starts_at']->format('g:i A') }}</li>
                        <li>{{ $nextRun['venue'] }}, {{ $nextRun['address'] }}</li>
                    </ul>
                    <a class="btn btn-yellow btn-sm" href="{{ $nextRun['map_url'] }}" target="_blank" rel="noopener">Open in Maps ↗</a>
                </div>
                <div class="clock" id="countdown" role="timer" aria-label="Countdown to the run" data-starts-at="{{ $nextRun['starts_at']->toIso8601String() }}">
                    <div class="clock-unit"><span class="clock-num" id="c-d">00</span><span class="clock-lbl">Days</span></div>
                    <span class="clock-sep" aria-hidden="true">:</span>
                    <div class="clock-unit"><span class="clock-num" id="c-h">00</span><span class="clock-lbl">Hrs</span></div>
                    <span class="clock-sep" aria-hidden="true">:</span>
                    <div class="clock-unit"><span class="clock-num" id="c-m">00</span><span class="clock-lbl">Min</span></div>
                    <span class="clock-sep" aria-hidden="true">:</span>
                    <div class="clock-unit"><span class="clock-num" id="c-s">00</span><span class="clock-lbl">Sec</span></div>
                </div>
            </div>
            <p class="clock-note" id="clock-note">{{ $nextRun['note'] }}</p>
        </div>
    </div>
</section>

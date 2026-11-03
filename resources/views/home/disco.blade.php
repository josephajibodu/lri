@php($startsAt = $discoRun['starts_at'])
<section class="hd dr-tone-{{ $discoRun['channels'][0]['tone'] }}" id="silent-disco" aria-labelledby="hd-title">
    <div class="hd-glow" aria-hidden="true"></div>
    <div class="hd-stars" aria-hidden="true">
        @foreach ([[8, 14, 1], [46, 6, .6], [91, 10, .8], [62, 34, .5], [4, 58, .7], [52, 88, .9], [96, 72, .6], [30, 96, .5]] as [$x, $y, $size])
            <span style="--x: {{ $x }}%; --y: {{ $y }}%; --s: {{ $size }}; --i: {{ $loop->index }}"></span>
        @endforeach
    </div>

    <div class="wrap hd-grid">
        <div class="hd-copy">
            <p class="hd-kicker"><span class="hd-live"></span>New · Let’s Run Ibadan presents</p>

            <h2 class="hd-title" id="hd-title" aria-label="Silent Disco Run">
                <span class="hd-silent" aria-hidden="true">Silent</span>
                <span class="hd-disco" aria-hidden="true">Disco</span>
                <span class="hd-run" aria-hidden="true">Run</span>
            </h2>

            <p class="hd-lede">Headsets on. The city hears nothing, you hear everything. A night run through Ibadan on three live DJ channels, then we rave at the finish.</p>

            <div class="hd-when">
                @if ($startsAt)
                    <span class="hd-time">{{ $startsAt->format('g A') }}</span>
                    <span class="hd-date">{{ $startsAt->format('D') }} <b>{{ $startsAt->format('j M') }}</b></span>
                @else
                    <span class="hd-date">{{ $discoRun['date_window'] }}</span>
                @endif
                <span class="hd-place">
                    <svg viewBox="0 0 24 24" aria-hidden="true"><path d="M12 22s7-7.2 7-12.5A7 7 0 0 0 5 9.5C5 14.8 12 22 12 22Z"></path><circle cx="12" cy="9.5" r="2.5"></circle></svg>
                    {{ $discoRun['venue'] ?? 'Ibadan' }}
                </span>
            </div>

            @if ($startsAt)
                <div class="hd-clock" data-hd-clock data-starts-at="{{ $startsAt->toIso8601String() }}" role="timer" aria-label="Time until the Silent Disco Run">
                    @foreach (['days' => 'Days', 'hours' => 'Hrs', 'minutes' => 'Min', 'seconds' => 'Sec'] as $unit => $label)
                        <div class="hd-cell"><span class="hd-num" data-unit="{{ $unit }}">00</span><span class="hd-lbl">{{ $label }}</span></div>
                    @endforeach
                </div>
            @endif

            <div class="hd-tuner" role="group" aria-label="Pick a channel">
                @foreach ($discoRun['channels'] as $channel)
                    <button type="button" class="hd-tune dr-tone-{{ $channel['tone'] }}" data-hd-tone="{{ $channel['tone'] }}" aria-pressed="{{ $loop->first ? 'true' : 'false' }}">
                        <svg viewBox="0 0 24 24" aria-hidden="true"><path d="M4 15v-3a8 8 0 0 1 16 0v3"></path><rect x="2.5" y="13" width="5" height="8" rx="2"></rect><rect x="16.5" y="13" width="5" height="8" rx="2"></rect></svg>
                        <span class="hd-tune-name">{{ $channel['name'] }}</span>
                    </button>
                @endforeach
            </div>
            <p class="hd-now" aria-live="polite">Tap a headset. Now playing <strong data-hd-now>{{ $discoRun['channels'][0]['name'] }}</strong></p>

            <div class="hd-cta">
                @if ($discoRun['price'])
                    <p class="hd-price"><span>Ticket</span><b>{{ $discoRun['price'] }}</b>@if ($discoRun['price_note'])<small>{{ $discoRun['price_note'] }}</small>@endif</p>
                @endif
                <div class="cta-row">
                    <a class="btn hd-btn" href="{{ route('disco-run') }}#tickets">Secure your spot →</a>
                    <a class="btn hd-btn-ghost" href="{{ route('disco-run') }}">See how it works</a>
                </div>
            </div>
        </div>

        <div class="hd-stage">
            <div class="hd-halo" aria-hidden="true"></div>
            <a class="hd-poster" href="{{ route('disco-run') }}" data-hd-tilt>
                <span class="hd-tape" aria-hidden="true"></span>
                <img src="{{ asset($discoRun['poster']) }}" width="800" height="1000" alt="Silent Disco Run flyer: runners in glowing headphones. {{ $startsAt ? $startsAt->format('l j F, g A') : $discoRun['date_window'] }}, {{ $discoRun['venue'] ?? 'Ibadan' }}.{{ $discoRun['price'] ? ' Tickets '.$discoRun['price'].'.' : '' }}" loading="lazy" decoding="async">
            </a>
            <div class="hd-sticker" aria-hidden="true">
                <svg viewBox="0 0 136 136">
                    <defs><path id="hd-sticker-circle" d="M68 68 m-50 0 a50 50 0 1 1 100 0 a50 50 0 1 1 -100 0"></path></defs>
                    <circle class="hd-sticker-disc" cx="68" cy="68" r="67"></circle>
                    <g class="hd-sticker-spin"><text class="hd-sticker-ring"><textPath href="#hd-sticker-circle" textLength="310" lengthAdjust="spacing">Headphones on · Volume up · Run ·</textPath></text></g>
                    <path class="hd-sticker-icon" d="M50 76v-8a18 18 0 0 1 36 0v8"></path>
                    <rect class="hd-sticker-cup" x="45" y="70" width="11" height="17" rx="4"></rect>
                    <rect class="hd-sticker-cup" x="80" y="70" width="11" height="17" rx="4"></rect>
                </svg>
            </div>
            <div class="hd-eq" aria-hidden="true">
                @for ($bar = 0; $bar < 18; $bar++)
                    <span style="--b: {{ $bar }}; --h: {{ [.35, .8, .55, 1, .45, .7, .9, .4][$bar % 8] }}"></span>
                @endfor
            </div>
        </div>
    </div>

    <div class="hd-ribbon" aria-hidden="true">
        <div class="hd-ribbon-track">
            @for ($group = 0; $group < 2; $group++)
                <span>One night</span><i>✦</i><span>One vibe</span><i>✦</i><span>Endless memories</span><i>✦</i><span>Silent Disco Run</span><i>✦</i>
            @endfor
        </div>
    </div>
</section>

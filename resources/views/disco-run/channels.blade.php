<section class="dr-sec dr-channels" id="channels">
    <div class="wrap dr-channels-grid">
        <div class="dr-channels-copy" data-reveal>
            <p class="dr-label">Three channels</p>
            <h2>Same road. <em>Different</em> song.</h2>
            <p class="dr-sub">Look around and you’ll see three colours of light bobbing down the road. Each colour is a different DJ. Tap a channel to tune in.</p>

            <div class="dr-tuner" role="group" aria-label="Pick a channel">
                @foreach ($discoRun['channels'] as $channel)
                    <button type="button" class="dr-tune dr-tone-{{ $channel['tone'] }}" data-channel-button="{{ $loop->index }}" data-tone="{{ $channel['tone'] }}" aria-pressed="{{ $loop->first ? 'true' : 'false' }}">
                        <span class="dr-tune-no">CH {{ $loop->iteration }}</span>
                        <span class="dr-tune-name">Vibe {{ $loop->iteration }}</span>
                        <span class="dr-tune-genre">Revealed on the night</span>
                    </button>
                @endforeach
            </div>
            <p class="dr-now" aria-live="polite">Tuned to <strong data-channel-name>Vibe 1</strong></p>
        </div>

        <div class="dr-crowd" aria-hidden="true">
            @for ($runner = 0; $runner < 48; $runner++)
                @php($on = ($runner * 7 + intdiv($runner, 5)) % count($discoRun['channels']))
                <span class="dr-headset dr-tone-{{ $discoRun['channels'][$on]['tone'] }}" data-on="{{ $on }}" style="--r: {{ ($runner * 37) % 100 / 100 }}"></span>
            @endfor
        </div>
    </div>
</section>

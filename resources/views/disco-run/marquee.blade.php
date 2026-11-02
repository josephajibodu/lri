<div class="dr-ticker" aria-hidden="true">
    @foreach (['', ' dr-ticker-rev'] as $direction)
        <div class="dr-ticker-row{{ $direction }}">
            <div class="dr-ticker-track">
                @for ($repeat = 0; $repeat < 2; $repeat++)
                    <span>Headsets on</span><span>✦</span><span>Run</span><span>✦</span><span>Vibe</span><span>✦</span><span>Rave</span><span>✦</span><span>Every pace</span><span>✦</span>
                @endfor
            </div>
        </div>
    @endforeach
</div>

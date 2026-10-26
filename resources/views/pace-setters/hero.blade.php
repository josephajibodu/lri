<section class="ps-hero" id="top">
    <div class="wrap ps-hero-grid">
        <div class="ps-hero-copy">
            <p class="label">Pace setters</p>
            <h1 class="wordmark ps-title"><span class="wm-line">The people who</span><span class="wm-line"><span class="wm-hl">set the pace.</span></span></h1>
            <p class="lede">Someone holds the pace at the front. Someone sweeps at the back so nobody runs alone. Meet the leaders of each tribe and the crew who keep the whole club moving.</p>
            <ul class="hero-facts">
                <li>{{ $people->count() }} {{ Str::plural('pace setter', $people->count()) }}</li>
                <li>{{ $groups->count() }} {{ Str::plural('lane', $groups->count()) }}</li>
                <li>Sat 6:30 AM</li>
            </ul>
            <div class="cta-row ps-cta">
                <a class="btn btn-yellow" href="#lanes">Find your pacer ↓</a>
                <a class="btn" href="{{ route('founders') }}">Meet the founders</a>
            </div>
        </div>

        <nav class="ps-board" aria-label="Pace setters by group">
            <p class="ps-board-strip"><span>Start line</span><span>Lane board</span></p>
            <ol class="ps-board-lanes">
                @foreach ($groups as $group)
                    <li>
                        <a class="ps-board-lane" href="#lane-{{ $group['slug'] }}" style="--dur: {{ 5 + $loop->index * 1.3 }}s; --delay: -{{ $loop->index * .8 }}s">
                            <span class="ps-board-no">{{ $group['lane'] }}</span>
                            <span class="ps-board-name">{{ $group['name'] }}</span>
                            <span class="ps-board-track" aria-hidden="true">
                                <span class="ps-pack">
                                    @foreach ($group['people'] as $person)
                                        <span class="ps-dot fd-tone-{{ $person['tone'] }}" style="--j: {{ $loop->index }}">{{ $person['initials'] }}</span>
                                    @endforeach
                                </span>
                            </span>
                            <span class="ps-board-count">{{ $group['people']->count() }}</span>
                        </a>
                    </li>
                @endforeach
            </ol>
        </nav>
    </div>
</section>

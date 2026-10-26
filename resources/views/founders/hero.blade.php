<section class="fd-hero" id="top">
    <div class="wrap fd-hero-grid">
        <div class="fd-hero-copy">
            <p class="label">The founders</p>
            <h1 class="wordmark fd-title"><span class="wm-line">Every club starts</span><span class="wm-line">with a <span class="wm-hl">first run.</span></span></h1>
            <p class="lede">Before the tribes, the hangouts and the Saturday crowds, Let’s Run Ibadan was a few people, one route and a 6:30 AM alarm. These are the people who set it going.</p>
            <ul class="hero-facts">
                <li>Est. {{ $founded }}</li>
                <li>{{ $people->count() }} {{ Str::plural('founder', $people->count()) }}</li>
                <li>4 tribes</li>
            </ul>
            <div class="cta-row fd-cta">
                <a class="btn btn-yellow" href="#start-list">Meet the founders ↓</a>
                <a class="btn" href="#story">Read the story</a>
            </div>
        </div>

        <div class="fd-fan" aria-hidden="true" style="--n: {{ min($people->count(), 4) }}">
            @foreach ($people->take(4) as $person)
                <div class="fd-fan-card" style="--i: {{ $loop->index }}">
                    <x-founder-portrait :person="$person" />
                    <span class="fd-fan-name">{{ Str::before($person['name'], ' ') }}</span>
                </div>
            @endforeach
            <span class="fd-est"><span><b>Est.</b><small>{{ $founded }}</small></span></span>
        </div>
    </div>
</section>

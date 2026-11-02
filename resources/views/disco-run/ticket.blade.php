<section class="dr-sec" id="tickets">
    <div class="wrap">
        <div class="dr-head" data-reveal>
            <p class="dr-label">Tickets</p>
            <h2>Grab a headset <em>before</em> they go.</h2>
        </div>

        <div data-reveal>
            <div class="dr-ticket" data-tilt>
                <div class="dr-ticket-main">
                    <p class="dr-ticket-brand">LRI · Admit one</p>
                    <p class="dr-ticket-title">{{ $discoRun['title'] }}</p>
                    <dl class="dr-ticket-meta">
                        <div><dt>When</dt><dd>{{ $when }}</dd></div>
                        <div><dt>Distance</dt><dd>{{ $discoRun['distance'] }} + rave</dd></div>
                        <div><dt>Where</dt><dd>{{ $discoRun['venue'] ?? 'Ibadan · venue drops soon' }}</dd></div>
                    </dl>
                    <ul class="dr-includes">
                        @foreach ($discoRun['includes'] as $item)
                            <li>{{ $item }}</li>
                        @endforeach
                    </ul>
                </div>

                <div class="dr-ticket-stub">
                    <p class="dr-price">{{ $discoRun['price'] ?? 'Price drops soon' }}</p>

                    @if ($checkout['type'] === 'embed')
                        <div class="dr-embed">
                            <script src="{{ $checkout['script'] }}" data-product="{{ $checkout['product'] }}" data-layout="basic" data-theme="dark" data-color="#FEB203"></script>
                        </div>
                    @elseif ($checkout['type'] === 'link')
                        <a class="btn dr-btn" href="{{ $checkout['url'] }}" target="_blank" rel="noopener">Pay for your ticket →</a>
                        <p class="dr-stub-note">Secure checkout. Your ticket and pickup details come by email.</p>
                    @else
                        <span class="btn dr-btn" aria-disabled="true">Tickets open soon</span>
                        <p class="dr-stub-note">Follow <a href="{{ config('lri.links.instagram') }}" target="_blank" rel="noopener">@@letsrunibadan</a> for the drop.</p>
                    @endif
                </div>
            </div>
        </div>
    </div>
</section>

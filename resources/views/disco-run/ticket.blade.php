<section class="dr-sec" id="tickets">
    <div class="wrap">
        <div class="dr-head" data-reveal>
            <p class="dr-label">Tickets</p>
            <h2>Grab a headset <em>before</em> they go.</h2>
        </div>

        <div data-reveal>
            <div @class(['dr-ticket', 'dr-ticket-checkout' => $ticketing['state'] === 'on_sale']) @if ($ticketing['state'] !== 'on_sale') data-tilt @endif>
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
                    @if ($ticketing['state'] === 'on_sale')
                        @php($ticketType = $ticketing['ticketType'])

                        <form class="dr-checkout" method="POST" action="{{ route('orders.store') }}" data-checkout novalidate>
                            @csrf
                            <input type="hidden" name="ticket_type" value="{{ $ticketType->slug }}">

                            <div class="dr-checkout-price">
                                <p class="dr-price" data-checkout-total data-unit-price="{{ $ticketType->price }}" data-currency="{{ $ticketType->currency }}">{{ $ticketType->unitPrice()->times((int) old('quantity', 1))->format() }}</p>
                                <p class="dr-stub-note">{{ $ticketType->unitPrice()->format() }} a ticket · {{ $ticketType->description ?? $discoRun['price_note'] }}</p>
                            </div>

                            @if ($errors->any())
                                <p class="dr-checkout-alert" role="alert">Something needs fixing. Check the fields marked below.</p>
                            @endif

                            <div class="dr-field">
                                <label for="quantity">Tickets</label>
                                <select class="dr-input" id="quantity" name="quantity" data-checkout-quantity>
                                    @foreach (range(1, $ticketing['maxQuantity']) as $count)
                                        <option value="{{ $count }}" @selected((int) old('quantity', 1) === $count)>{{ $count }}</option>
                                    @endforeach
                                </select>
                                <p class="dr-field-error" data-error-for="quantity">@error('quantity'){{ $message }}@enderror</p>
                            </div>

                            <div class="dr-field">
                                <label for="name">Your name</label>
                                <input class="dr-input" id="name" name="name" type="text" value="{{ old('name') }}" required autocomplete="name" maxlength="120">
                                <p class="dr-field-error" data-error-for="name">@error('name'){{ $message }}@enderror</p>
                            </div>

                            <div class="dr-field">
                                <label for="email">Email</label>
                                <input class="dr-input" id="email" name="email" type="email" value="{{ old('email') }}" required autocomplete="email" inputmode="email" aria-describedby="email-hint">
                                <p class="dr-field-hint" id="email-hint">Your ticket comes here.</p>
                                <p class="dr-field-error" data-error-for="email">@error('email'){{ $message }}@enderror</p>
                            </div>

                            <div class="dr-field">
                                <label for="phone">Phone <span class="dr-optional">Optional</span></label>
                                <input class="dr-input" id="phone" name="phone" type="tel" value="{{ old('phone') }}" autocomplete="tel" inputmode="tel" placeholder="0803 123 4567" aria-describedby="phone-hint">
                                <p class="dr-field-hint" id="phone-hint">For headset pickup updates.</p>
                                <p class="dr-field-error" data-error-for="phone">@error('phone'){{ $message }}@enderror</p>
                            </div>

                            <label class="dr-check">
                                <input type="checkbox" name="marketing_consent" value="1" @checked(old('marketing_consent'))>
                                <span>Email me about LRI runs and events. You can stop any time.</span>
                            </label>

                            @include('checkout.methods', ['channels' => $ticketing['channels']])
                            <p class="dr-field-error" data-error-for="channel">@error('channel'){{ $message }}@enderror</p>
                        </form>
                    @elseif ($ticketing['state'] === 'sold_out')
                        <p class="dr-price">Sold out</p>
                        <span class="btn dr-btn" aria-disabled="true">No tickets left</span>
                        <p class="dr-stub-note">Follow <a href="{{ config('lri.links.instagram') }}" target="_blank" rel="noopener">@@letsrunibadan</a> in case more headsets drop.</p>
                    @else
                        <p class="dr-price">{{ $discoRun['price'] ?? 'Price drops soon' }}</p>
                        <span class="btn dr-btn" aria-disabled="true">Tickets open soon</span>
                        <p class="dr-stub-note">Follow <a href="{{ config('lri.links.instagram') }}" target="_blank" rel="noopener">@@letsrunibadan</a> for the drop.</p>
                    @endif
                </div>
            </div>
        </div>
    </div>
</section>

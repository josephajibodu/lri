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
                    @if ($ticketing['state'] === 'on_sale')
                        <div class="dr-ticket-photo">
                            <img src="{{ asset('images/disco-run/crowd.jpg') }}" width="1080" height="305" alt="" aria-hidden="true" loading="lazy" decoding="async">
                        </div>
                    @endif
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

                        <form class="dr-checkout" method="POST" action="{{ route('orders.store') }}" data-checkout data-checkout-form-start novalidate>
                            @csrf
                            <input type="hidden" name="ticket_type" value="{{ $ticketType->slug }}">

                            <div class="dr-checkout-summary">
                                <p class="dr-price" aria-live="polite" data-checkout-total data-unit-price="{{ $ticketType->price }}" data-currency="{{ $ticketType->currency }}">{{ $ticketType->unitPrice()->times((int) old('quantity', 1))->format() }}</p>
                                <p class="dr-stub-note">{{ $ticketType->unitPrice()->format() }} a ticket · {{ $ticketType->description ?? $discoRun['price_note'] }}</p>
                                <div class="dr-field dr-field-quantity">
                                    <label for="quantity">Tickets</label>
                                    <div class="dr-stepper">
                                        <button class="dr-stepper-btn" type="button" data-checkout-step="-1" aria-label="One ticket fewer" hidden>−</button>
                                        <input class="dr-stepper-input" id="quantity" name="quantity" type="number" inputmode="numeric" min="1" max="{{ $ticketing['maxQuantity'] }}" value="{{ old('quantity', 1) }}" data-checkout-quantity>
                                        <button class="dr-stepper-btn" type="button" data-checkout-step="1" aria-label="One ticket more" hidden>+</button>
                                    </div>
                                </div>
                                <p class="dr-field-error" data-error-for="quantity">@error('quantity'){{ $message }}@enderror</p>
                            </div>

                            @if ($errors->any())
                                <p class="dr-checkout-alert" role="alert">Something needs fixing. Check the fields marked below.</p>
                            @endif

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

                            <details class="dr-coupon" data-coupon data-coupon-url="{{ route('coupons.check') }}" @if (old('coupon') || $errors->has('coupon')) open @endif>
                                <summary>Have a coupon?</summary>
                                <div class="dr-field">
                                    <label for="coupon">Coupon code</label>
                                    <div class="dr-coupon-row">
                                        <input class="dr-input" id="coupon" name="coupon" type="text" value="{{ old('coupon') }}" maxlength="40" autocomplete="off" autocapitalize="characters" spellcheck="false" data-coupon-input>
                                        <button class="btn btn-sm dr-btn" type="button" data-coupon-apply hidden>Apply</button>
                                        <button class="dr-order-link" type="button" data-coupon-remove hidden>Remove</button>
                                    </div>
                                    <p class="dr-field-hint" data-coupon-hint>It comes off your total when you choose how to pay.</p>
                                    <p class="dr-coupon-applied" role="status" data-coupon-applied hidden></p>
                                    <p class="dr-field-error" data-error-for="coupon">@error('coupon'){{ $message }}@enderror</p>
                                </div>
                            </details>

                            <p class="dr-pay-heading" aria-hidden="true">Pay with</p>
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

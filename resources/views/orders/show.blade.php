<x-layouts.app title="Your order {{ $order->reference }} · Let’s Run Ibadan" robots="noindex, nofollow">
    <x-site.header />

    <main class="dr dr-tone-{{ $discoRun['channels'][0]['tone'] }}">
        <section class="dr-sec dr-order-sec">
            <div class="wrap">
                <div class="dr-order" @if ($state === 'waiting') data-order-poll="{{ $pollUrl }}" data-state="{{ $state }}" @endif>
                    <p class="dr-label">Order {{ $order->reference }}</p>

                    @if (session('checkout_error'))
                        <p class="dr-checkout-alert" role="alert">{{ session('checkout_error') }}</p>
                    @endif

                    @switch($state)
                        @case('waiting')
                            @if ($from === 'checkout')
                                <h1 class="dr-order-title">Confirming your <em>payment</em>…</h1>
                                <p class="dr-sub">This page updates by itself. Bank transfers can take a minute or two to arrive, so there’s no need to pay again.</p>
                                <div class="dr-order-pulse" aria-hidden="true"><span></span><span></span><span></span></div>
                            @else
                                <h1 class="dr-order-title">You haven’t <em>paid</em> yet.</h1>
                                <p class="dr-sub">Your {{ Str::plural('ticket', $order->quantity) }} {{ $order->quantity === 1 ? 'is' : 'are' }} held until {{ $order->expires_at->timezone('Africa/Lagos')->format('g:i A') }}. If you already sent a bank transfer, give it a minute; this page updates by itself.</p>
                            @endif
                            @break

                        @case('failed')
                            <h1 class="dr-order-title">Your payment didn’t <em>go through</em>.</h1>
                            <p class="dr-sub">{{ $order->latestPayment->failure_reason?->advice() ?? 'Try again, or choose another way to pay.' }}</p>
                            @break

                        @case('underpaid')
                            <h1 class="dr-order-title">We got <em>less</em> than your total.</h1>
                            <p class="dr-sub">The amount that arrived didn’t cover {{ $order->total()->format() }}. Message <a href="{{ config('lri.links.instagram') }}" target="_blank" rel="noopener">@@letsrunibadan</a> with your order number and we’ll sort it out.</p>
                            @break

                        @case('abandoned')
                            <h1 class="dr-order-title">This order <em>expired</em>.</h1>
                            <p class="dr-sub">It wasn’t paid in time, so its tickets went back on sale. Pay now to get them back while there are still some left.</p>
                            @break

                        @case('refunded')
                            <h1 class="dr-order-title">This order was <em>refunded</em>.</h1>
                            <p class="dr-sub">The money went back to where it came from. Message <a href="{{ config('lri.links.instagram') }}" target="_blank" rel="noopener">@@letsrunibadan</a> if anything looks wrong.</p>
                            @break

                        @default
                            <h1 class="dr-order-title">You haven’t <em>paid</em> yet.</h1>
                            <p class="dr-sub">
                                @if ($order->expires_at?->isFuture())
                                    Your {{ Str::plural('ticket', $order->quantity) }} {{ $order->quantity === 1 ? 'is' : 'are' }} held until {{ $order->expires_at->timezone('Africa/Lagos')->format('g:i A') }}.
                                @endif
                                Choose how you’d like to pay.
                            </p>
                    @endswitch

                    <dl class="dr-order-summary">
                        <div><dt>Tickets</dt><dd>{{ $order->quantity }} × {{ $order->unitPrice()->format() }}</dd></div>
                        <div><dt>Total</dt><dd>{{ $order->total()->format() }}</dd></div>
                        <div><dt>Name</dt><dd>{{ $order->buyer_name }}</dd></div>
                        <div><dt>Email</dt><dd>{{ $order->buyer_email }}</dd></div>
                    </dl>

                    @if (in_array($state, ['waiting', 'failed', 'unpaid', 'abandoned'], true) && $channels->isNotEmpty())
                        <form class="dr-checkout dr-order-retry" method="POST" action="{{ $payUrl }}" data-checkout>
                            @csrf
                            <p class="dr-label">{{ $state === 'waiting' && $from === 'checkout' ? 'Something went wrong?' : 'Pay now' }}</p>
                            @include('checkout.methods', ['channels' => $channels])
                            @error('channel')
                                <p class="dr-field-error">{{ $message }}</p>
                            @enderror
                        </form>
                    @endif

                    <a class="dr-order-back" href="{{ route('disco-run') }}">← Back to the Silent Disco Run</a>
                </div>
            </div>
        </section>
    </main>

    <x-site.footer />
</x-layouts.app>

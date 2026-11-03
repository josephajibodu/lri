<x-layouts.app title="Your receipt {{ $order->reference }} · Let’s Run Ibadan" robots="noindex, nofollow">
    <x-site.header />

    <main class="dr dr-tone-{{ $discoRun['channels'][0]['tone'] }}">
        <section class="dr-sec dr-order-sec">
            <div class="wrap">
                <div class="dr-order">
                    <p class="dr-label">Receipt · Order {{ $order->reference }}</p>

                    <h1 class="dr-order-title">You’re <em>in</em>.</h1>
                    <p class="dr-sub">Your {{ $order->quantity === 1 ? 'ticket is' : $order->quantity.' tickets are' }} paid for. Show {{ $order->quantity === 1 ? 'this code' : 'these codes' }} when you pick up your headset.</p>
                    <ul class="dr-order-tickets">
                        @foreach ($order->tickets as $ticket)
                            <li>
                                <span class="dr-order-ticket-label">Ticket {{ $loop->iteration }}</span>
                                <span class="dr-order-ticket-code">{{ $ticket->code }}</span>
                            </li>
                        @endforeach
                    </ul>
                    <p class="dr-stub-note">
                        @if ($order->tickets_emailed_at)
                            We’ve emailed your tickets and this receipt to {{ $order->buyer_email }}.
                        @else
                            Your tickets and this receipt are on their way to {{ $order->buyer_email }}.
                        @endif
                        Bookmark this page: its address is your proof of purchase, and the button in the email opens it too.
                    </p>

                    <dl class="dr-order-summary">
                        <div><dt>Event</dt><dd>{{ $event['title'] ?? $order->ticketType->name }}</dd></div>
                        @if ($when)
                            <div><dt>When</dt><dd>{{ $when }}</dd></div>
                        @endif
                        @if (filled($event['venue'] ?? null))
                            <div><dt>Where</dt><dd>{{ $event['venue'] }}</dd></div>
                        @endif
                        <div><dt>Tickets</dt><dd>{{ $order->quantity }} × {{ $order->unitPrice()->format() }}</dd></div>
                        <div><dt>Total paid</dt><dd>{{ $order->total()->format() }}</dd></div>
                        @if ($order->paid_at)
                            <div><dt>Paid on</dt><dd>{{ $order->paid_at->timezone('Africa/Lagos')->format('j M Y, g:i A') }}</dd></div>
                        @endif
                        @if ($payment?->method)
                            <div><dt>Paid with</dt><dd>{{ $payment->method->label() }}</dd></div>
                        @endif
                        @if ($payment)
                            <div><dt>Payment reference</dt><dd>{{ $payment->reference }}</dd></div>
                        @endif
                        <div><dt>Name</dt><dd>{{ $order->buyer_name }}</dd></div>
                        <div><dt>Email</dt><dd>{{ $order->buyer_email }}</dd></div>
                    </dl>

                    <a class="dr-order-back" href="{{ route('disco-run') }}">← Back to the Silent Disco Run</a>
                </div>
            </div>
        </section>
    </main>

    <x-site.footer />
</x-layouts.app>

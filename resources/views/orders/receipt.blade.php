<x-layouts.app title="Your receipt {{ $order->reference }} · Let’s Run Ibadan" robots="noindex, nofollow" theme-color="#EEECE7">
    <x-site.header />

    <main class="dr dr-tone-{{ $discoRun['channels'][0]['tone'] }}">
        <section class="dr-sec dr-order-sec">
            <div class="wrap">
                <div class="dr-order">
                    <p class="dr-label">Receipt · Order {{ $order->reference }}</p>

                    <h1 class="dr-order-title">You’re <em>in</em>.</h1>
                    <p class="dr-sub">Your {{ $order->quantity === 1 ? 'ticket is' : $order->quantity.' tickets are' }} paid for. Show your QR code when you pick up your headset.</p>
                    @if (session('status'))
                        <p class="dr-order-notice" role="status">{{ session('status') }}</p>
                    @endif
                    <ul class="dr-order-tickets">
                        @foreach ($order->tickets as $ticket)
                            @php($ticketErrors = $errors->getBag('ticket'.$ticket->id))
                            @php($status = $ticket->receiptStatus())
                            <li class="dr-order-ticket" id="ticket-{{ $ticket->id }}">
                                <div class="dr-order-ticket-head">
                                    <span class="dr-order-ticket-label">Ticket {{ $loop->iteration }}</span>
                                    <span class="dr-order-ticket-status dr-order-ticket-status-{{ $status }}">{{ match ($status) {
                                        'collected' => 'Headset collected',
                                        'yours' => 'Yours',
                                        'emailed' => 'Emailed',
                                        'link_sent' => 'Link sent',
                                        default => 'No name yet',
                                    } }}</span>
                                </div>

                                @if ($ticket->isNamed())
                                    <p class="dr-order-ticket-holder">{{ $ticket->holder_name }} · {{ $ticket->holder_email }}</p>
                                @endif

                                @if ($status === 'yours')
                                    <x-ticket-qr :ticket="$ticket" />
                                @elseif ($status === 'collected')
                                    <span class="dr-order-ticket-code">{{ $ticket->code }}</span>
                                @else
                                    <span class="dr-order-ticket-code">{{ $ticket->code }}</span>
                                    <p class="dr-field-error">{{ $ticketErrors->first('ticket') }}</p>

                                    @if ($status === 'emailed')
                                        @include('orders.ticket-name-form', ['summary' => 'Edit', 'button' => 'Save and email their ticket again'])
                                        <form class="dr-order-ticket-actions" method="POST" action="{{ route('receipts.tickets.reissue', [$order->receipt_token, $ticket]) }}" data-confirm="Reissue this ticket? It gets a new code, and the old code, QR code and link stop working. You’ll then name it again or send a new link.">
                                            @csrf
                                            <button class="dr-order-link" type="submit">Reissue</button>
                                            <span class="dr-field-hint">New code; the old one stops working.</span>
                                        </form>
                                    @elseif ($status === 'link_sent')
                                        <div class="dr-order-ticket-actions">
                                            <button class="btn btn-sm dr-btn" type="button" data-copy="{{ $ticket->claimUrl() }}" data-copied="Link copied">Copy link</button>
                                            <form method="POST" action="{{ route('receipts.tickets.cancel-link', [$order->receipt_token, $ticket]) }}" data-confirm="Cancel this link? Anyone who has it won’t be able to use it.">
                                                @csrf
                                                @method('DELETE')
                                                <button class="dr-order-link" type="submit">Cancel link</button>
                                            </form>
                                        </div>
                                    @else
                                        @include('orders.ticket-name-form', ['summary' => 'Type details', 'button' => 'Save and email their ticket'])
                                        <form class="dr-order-ticket-actions" method="POST" action="{{ route('receipts.tickets.share-link', [$order->receipt_token, $ticket]) }}" data-share="{{ $ticket->claimUrl() }}">
                                            @csrf
                                            <button class="btn btn-sm dr-btn" type="submit">Send link</button>
                                            <span class="dr-field-hint">They fill in their own name and email.</span>
                                        </form>
                                    @endif
                                @endif
                            </li>
                        @endforeach
                    </ul>
                    @if ($order->quantity > 1)
                        <p class="dr-stub-note">Each person collects their own headset with their own ticket. Type in each person’s details, or send them their ticket’s link to fill in themselves. Anything left blank can be filled in at the desk on the day.</p>
                    @endif
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

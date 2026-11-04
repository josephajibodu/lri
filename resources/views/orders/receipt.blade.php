<x-layouts.app title="Your receipt {{ $order->reference }} · Let’s Run Ibadan" robots="noindex, nofollow" theme-color="#EEECE7">
    <x-site.header />

    <main class="dr dr-tone-{{ $discoRun['channels'][0]['tone'] }}">
        <section class="dr-sec dr-order-sec">
            <div class="wrap">
                <div class="dr-order">
                    <p class="dr-label">Receipt · Order {{ $order->reference }}</p>

                    <h1 class="dr-order-title">You’re <em>in</em>.</h1>
                    <p class="dr-sub">Your {{ $order->quantity === 1 ? 'ticket is' : $order->quantity.' tickets are' }} paid for. Show {{ $order->quantity === 1 ? 'this code' : 'these codes' }} when you pick up your headset.</p>
                    @if (session('status'))
                        <p class="dr-order-notice" role="status">{{ session('status') }}</p>
                    @endif
                    <ul class="dr-order-tickets">
                        @foreach ($order->tickets as $ticket)
                            @php($ticketErrors = $errors->getBag('ticket'.$ticket->id))
                            <li class="dr-order-ticket" id="ticket-{{ $ticket->id }}">
                                <div class="dr-order-ticket-head">
                                    <span class="dr-order-ticket-label">Ticket {{ $loop->iteration }}</span>
                                    <span class="dr-order-ticket-code">{{ $ticket->code }}</span>
                                </div>
                                @if ($ticket->isCollected())
                                    <p class="dr-order-ticket-holder">{{ $ticket->holder_name }} · headset collected</p>
                                @elseif ($order->quantity > 1)
                                    @if ($ticket->isNamed() && $ticketErrors->isEmpty())
                                        <p class="dr-order-ticket-holder">{{ $ticket->holder_name }} · {{ $ticket->holder_email }}</p>
                                    @endif
                                    <details class="dr-order-ticket-name" @if (! $ticket->isNamed() || $ticketErrors->isNotEmpty()) open @endif>
                                        <summary>{{ $ticket->isNamed() ? 'Change name or email' : 'Who is this ticket for?' }}</summary>
                                        <form method="POST" action="{{ route('receipts.tickets.update', [$order->receipt_token, $ticket]) }}">
                                            @csrf
                                            @method('PUT')
                                            <div class="dr-field">
                                                <label for="name-{{ $ticket->id }}">Their name</label>
                                                <input class="dr-input" id="name-{{ $ticket->id }}" name="name" type="text" maxlength="120" required autocomplete="off" value="{{ $ticketErrors->isNotEmpty() ? old('name') : $ticket->holder_name }}" @if ($ticketErrors->has('name')) aria-invalid="true" @endif>
                                                <p class="dr-field-error">{{ $ticketErrors->first('name') }}</p>
                                            </div>
                                            <div class="dr-field">
                                                <label for="email-{{ $ticket->id }}">Their email</label>
                                                <input class="dr-input" id="email-{{ $ticket->id }}" name="email" type="email" maxlength="190" required autocomplete="off" inputmode="email" value="{{ $ticketErrors->isNotEmpty() ? old('email') : $ticket->holder_email }}" @if ($ticketErrors->has('email')) aria-invalid="true" @endif>
                                                <p class="dr-field-error">{{ $ticketErrors->first('email') }}</p>
                                            </div>
                                            <p class="dr-field-error">{{ $ticketErrors->first('ticket') }}</p>
                                            <button class="btn btn-sm dr-btn" type="submit">Save and email their ticket</button>
                                        </form>
                                        @unless ($ticket->isNamed())
                                            <p class="dr-field-hint">
                                                Or let them fill it in:
                                                <button class="dr-order-copy" type="button" data-copy="{{ $ticket->claimUrl() }}" data-copied="Link copied">Copy their link</button>
                                            </p>
                                        @endunless
                                    </details>
                                @endif
                            </li>
                        @endforeach
                    </ul>
                    @if ($order->quantity > 1)
                        <p class="dr-stub-note">Each person collects their own headset, so each ticket needs their name and email. Fill them in above, or copy a ticket’s link and send it to that friend. Anything left blank can be filled in at the desk on the day.</p>
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

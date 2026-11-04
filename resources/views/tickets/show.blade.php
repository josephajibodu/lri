<x-layouts.app title="Your ticket {{ $ticket->code }} · Let’s Run Ibadan" robots="noindex, nofollow" theme-color="#EEECE7">
    <x-site.header />

    <main class="dr dr-tone-{{ $discoRun['channels'][0]['tone'] }}">
        <section class="dr-sec dr-order-sec">
            <div class="wrap">
                <div class="dr-order">
                    <p class="dr-label">Ticket · {{ $event['title'] ?? $ticket->ticketType->name }}</p>

                    @php($ticketErrors = $errors->getBag('ticket'.$ticket->id))

                    @if ($ticket->isNamed())
                        <h1 class="dr-order-title">You’re <em>in</em>, {{ Str::before($ticket->holder_name, ' ') }}.</h1>
                        @if (session('status'))
                            <p class="dr-order-notice" role="status">{{ session('status') }}</p>
                        @endif
                        <p class="dr-sub">Show this QR code at the desk when you pick up your headset. If it won’t scan, they can type the code under it.</p>
                        <p class="dr-field-error">{{ $ticketErrors->first('ticket') }}</p>
                    @else
                        <h1 class="dr-order-title">Your <em>ticket</em>.</h1>
                        <p class="dr-sub">Someone got you a ticket. Add your name and email so it’s yours, and we’ll email it to you.</p>
                    @endif

                    @if ($ticket->isNamed())
                        <x-ticket-qr :ticket="$ticket" />
                    @else
                        <ul class="dr-order-tickets">
                            <li>
                                <span class="dr-order-ticket-label">Ticket</span>
                                <span class="dr-order-ticket-code">{{ $ticket->code }}</span>
                            </li>
                        </ul>
                    @endif

                    @unless ($ticket->isNamed())
                        <form class="dr-order-claim" method="POST" action="{{ route('tickets.claim', $ticket->claim_token) }}">
                            @csrf
                            <div class="dr-field">
                                <label for="name">Your name</label>
                                <input class="dr-input" id="name" name="name" type="text" maxlength="120" required autocomplete="name" value="{{ old('name') }}" @if ($ticketErrors->has('name')) aria-invalid="true" @endif>
                                <p class="dr-field-error">{{ $ticketErrors->first('name') }}</p>
                            </div>
                            <div class="dr-field">
                                <label for="email">Your email</label>
                                <input class="dr-input" id="email" name="email" type="email" maxlength="190" required autocomplete="email" inputmode="email" value="{{ old('email') }}" @if ($ticketErrors->has('email')) aria-invalid="true" @endif>
                                <p class="dr-field-error">{{ $ticketErrors->first('email') }}</p>
                            </div>
                            <p class="dr-field-error">{{ $ticketErrors->first('ticket') }}</p>
                            <button class="btn dr-btn" type="submit">This ticket is mine</button>
                        </form>
                    @endunless

                    <dl class="dr-order-summary">
                        <div><dt>Event</dt><dd>{{ $event['title'] ?? $ticket->ticketType->name }}</dd></div>
                        @if ($when)
                            <div><dt>When</dt><dd>{{ $when }}</dd></div>
                        @endif
                        @if (filled($event['venue'] ?? null))
                            <div><dt>Where</dt><dd>{{ $event['venue'] }}</dd></div>
                        @endif
                        @if ($ticket->isNamed())
                            <div><dt>Name</dt><dd>{{ $ticket->holder_name }}</dd></div>
                            <div><dt>Email</dt><dd>{{ $ticket->holder_email }}</dd></div>
                        @endif
                        <div><dt>Headset</dt><dd>{{ $ticket->isCollected() ? 'Collected' : 'Not collected yet' }}</dd></div>
                    </dl>

                    <a class="dr-order-back" href="{{ route('disco-run') }}">← Back to the Silent Disco Run</a>
                </div>
            </div>
        </section>
    </main>

    <x-site.footer />
</x-layouts.app>

@props(['sale', 'event', 'href', 'kicker' => 'Sale'])

{{-- A sale every buyer gets, counting down to its end so buyers can see the lower price won't last.
     The script keeps the clock ticking and, at zero, swaps the bar for a note that the sale is over. --}}
@php
    $timeLeft = $sale->timeLeft();
    $endsIn = $sale->endsInWords();
    $ticketLimit = $sale->coupon->max_tickets_per_order;
    $fullPrice = $sale->ticketType->unitPrice()->format();
@endphp
<aside @class(['sale-bar', 'is-last-day' => $sale->isLastDay()]) aria-label="Ticket sale" data-sale-bar @if ($timeLeft) data-sale-ends-in="{{ $sale->secondsLeft() }}" @endif>
    <div class="wrap sale-bar-row" data-while-sale>
        <p class="sale-bar-deal">
            <span class="sale-bar-kicker">{{ $kicker }}</span>
            <span class="sale-bar-off">{{ $sale->coupon->amountOff() }}</span>
        </p>

        <div class="sale-bar-copy">
            <p class="sale-bar-headline">{{ $event }} tickets, now <strong>{{ $sale->price()->format() }}</strong> <s><span class="sr-only">instead of </span>{{ $fullPrice }}</s></p>
            @if ($endsIn || $ticketLimit)
                <p class="sale-bar-terms">
                    @if ($endsIn)
                        <span class="sale-bar-ends"><span class="sale-bar-dot" aria-hidden="true"></span>Ends {{ $endsIn }}</span>
                    @endif
                    @if ($ticketLimit)
                        <span>On up to {{ $ticketLimit }} {{ Str::plural('ticket', $ticketLimit) }} an order</span>
                    @endif
                </p>
            @endif
        </div>

        @if ($timeLeft)
            <p class="sale-bar-clock" aria-hidden="true">
                <span class="sale-bar-in">Ends in</span>
                @foreach (['d', 'h', 'm', 's'] as $unit)
                    <span class="sale-bar-unit" data-sale-unit="{{ $unit }}" @if ($unit === 'd' && $timeLeft['d'] === 0) hidden @endif><b>{{ sprintf('%02d', $timeLeft[$unit]) }}</b><i>{{ $unit }}</i></span>
                @endforeach
            </p>
        @endif

        <a class="sale-bar-cta" href="{{ $href }}" aria-label="Get {{ $sale->coupon->amountOff() }} tickets"><span class="sale-bar-cta-long">Get {{ $sale->coupon->amountOff() }}</span><span class="sale-bar-cta-short">Get it</span> <span aria-hidden="true">→</span></a>
    </div>

    <div class="wrap sale-bar-row sale-bar-over" data-after-sale hidden>
        <p>The sale has ended. Tickets are back to {{ $fullPrice }}.</p>
    </div>

    @if ($timeLeft)
        <span class="sale-bar-track" aria-hidden="true" data-while-sale><span class="sale-bar-fill" style="--left: {{ $sale->shareLeft() }}" data-sale-fill></span></span>
    @endif
</aside>

<x-mail::message :preheader="($order->quantity === 1 ? 'Your ticket code' : 'Your ticket codes').' and receipt for the '.$eventName.'.'" :banner-url="$bannerUrl" :banner-alt="$eventName">
<x-mail::eyebrow :accent="$accent">Payment confirmed</x-mail::eyebrow>

# You’re in, {{ $firstName }}.

Your {{ $order->quantity === 1 ? 'ticket' : $order->quantity.' tickets' }} for the **{{ $eventName }}** {{ $order->quantity === 1 ? 'is' : 'are' }} paid for.

<x-mail::ticket-pass :event-name="$eventName" :details="$details" :ticket="$ownTicket" :message="$message ?? null" :label="$ownTicket ? 'Admit one' : $order->quantity.' '.Str::plural('ticket', $order->quantity)" :accent="$accent" :gradient="$gradient" />

@if ($order->quantity > 1)
### All the tickets you paid for

<x-mail::ticket-codes :tickets="$order->tickets" :accent="$accent" :holders="true" />

@endif
@if ($unnamedTickets > 0)
<x-mail::panel>
Each person collects their own headset, so each ticket needs a name and email. Add them on your receipt, or send each friend their ticket’s link from there so they can fill it in. You can also bring everyone to the desk on the day.
</x-mail::panel>

@endif
<x-mail::button :url="$receiptUrl" :accent="$accent" :gradient="$gradient">
See your tickets
</x-mail::button>

<x-mail::note>Keep this email. The button opens your receipt any time, and it’s your proof of purchase.</x-mail::note>

<x-mail::details :rows="$receipt" />

@if ($channels)
<x-mail::channels :channels="$channels" :accent="$accent" />

@endif
<x-mail::sign-off :tagline="$tagline" />
</x-mail::message>

<x-mail::message :preheader="($order->quantity === 1 ? 'Your ticket code' : 'Your ticket codes').' and receipt for the '.$eventName.'.'" :banner-url="$bannerUrl" :banner-alt="$eventName">
# You’re in, {{ Str::before($order->buyer_name, ' ') }}.

Your {{ $order->quantity === 1 ? 'ticket' : $order->quantity.' tickets' }} for the **{{ $eventName }}** {{ $order->quantity === 1 ? 'is' : 'are' }} paid for. Show your QR code at the desk when you pick up your headset.

@if ($ownTicket)
<x-mail::ticket-qr :ticket="$ownTicket" :message="$message ?? null" />

@endif
@if ($order->quantity > 1)
All the tickets you paid for:

<x-mail::ticket-codes :tickets="$order->tickets" :accent="$accent" :holders="true" />
@endif

@if ($unnamedTickets > 0)
Each person collects their own headset, so each ticket needs a name and email. Add them on your receipt, or send each friend their ticket’s link from there so they can fill it in. You can also bring everyone to the desk on the day.
@endif

<x-mail::details :rows="$details" />

<x-mail::button :url="$receiptUrl">
See your tickets
</x-mail::button>

Keep this email. The button opens your receipt any time, and it’s your proof of purchase.

See you on the run,<br>
{{ config('lri.site.name') }}
</x-mail::message>

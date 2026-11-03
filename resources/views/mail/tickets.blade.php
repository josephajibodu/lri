<x-mail::message :preheader="($order->quantity === 1 ? 'Your ticket code' : 'Your ticket codes').' and receipt for the '.$eventName.'.'" :banner-url="$bannerUrl" :banner-alt="$eventName">
# You’re in, {{ Str::before($order->buyer_name, ' ') }}.

Your {{ $order->quantity === 1 ? 'ticket' : $order->quantity.' tickets' }} for the **{{ $eventName }}** {{ $order->quantity === 1 ? 'is' : 'are' }} paid for. Show {{ $order->quantity === 1 ? 'this code' : 'these codes' }} when you pick up your headset.

<x-mail::ticket-codes :tickets="$order->tickets" :accent="$accent" />

<x-mail::details :rows="$details" />

<x-mail::button :url="$receiptUrl">
See your tickets
</x-mail::button>

Keep this email. The button opens your receipt any time, and it’s your proof of purchase.

See you on the run,<br>
{{ config('lri.site.name') }}
</x-mail::message>

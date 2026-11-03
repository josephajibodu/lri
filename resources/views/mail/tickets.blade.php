<x-mail::message>
# You’re in, {{ Str::before($order->buyer_name, ' ') }}.

Your {{ $order->quantity === 1 ? 'ticket' : $order->quantity.' tickets' }} for the **{{ $event['title'] ?? $order->ticketType->name }}** {{ $order->quantity === 1 ? 'is' : 'are' }} paid for. Show {{ $order->quantity === 1 ? 'this code' : 'these codes' }} when you pick up your headset.

<x-mail::table>
| Ticket | Code |
|:-------|:-----|
@foreach ($order->tickets as $ticket)
| Ticket {{ $loop->iteration }} | **{{ $ticket->code }}** |
@endforeach
</x-mail::table>

@if ($when)
**When:** {{ $when }}<br>
@endif
@if (filled($event['venue'] ?? null))
**Where:** {{ $event['venue'] }}<br>
@endif
**Order:** {{ $order->reference }} · {{ $order->quantity }} × {{ $order->unitPrice()->format() }} = {{ $order->total()->format() }}

<x-mail::button :url="$orderUrl">
See your tickets
</x-mail::button>

Keep this email. The button opens your order page any time, and it’s your proof of purchase.

See you on the run,<br>
{{ config('lri.site.name') }}
</x-mail::message>

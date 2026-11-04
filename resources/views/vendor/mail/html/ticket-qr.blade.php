@props(['ticket', 'message' => null])
@php($png = App\Support\TicketQrCode::png($ticket))
<table class="ticket-qr" width="100%" cellpadding="0" cellspacing="0" role="presentation">
<tr>
<td align="center">
<img src="{{ $message ? $message->embedData($png, 'ticket-'.$ticket->code.'.png', 'image/png') : 'data:image/png;base64,'.base64_encode($png) }}" width="200" height="200" alt="QR code for ticket {{ $ticket->code }}" class="ticket-qr-image">
<p class="ticket-qr-code">{{ $ticket->code }}</p>
</td>
</tr>
</table>

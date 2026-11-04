@props(['ticket'])
<figure class="dr-qr">
    <div class="dr-qr-code" role="img" aria-label="QR code for ticket {{ $ticket->code }}">{!! App\Support\TicketQrCode::svg($ticket) !!}</div>
    <figcaption class="dr-qr-caption">{{ $ticket->code }}</figcaption>
</figure>

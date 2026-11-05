@props(['eventName', 'details' => [], 'ticket' => null, 'message' => null, 'label' => null, 'accent' => null, 'gradient' => null])
{{ $eventName }}
@foreach ($details as $name => $value)
{{ $name }}: {{ $value }}
@endforeach
@if ($ticket)
Name: {{ $ticket->holder_name }}

Your ticket code: {{ $ticket->code }}
Show it at the desk to collect your headset. The QR code is in the HTML version of this email and on your ticket page.
@endif

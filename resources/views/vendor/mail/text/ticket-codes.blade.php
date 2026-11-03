@props(['tickets'])
@foreach ($tickets as $ticket)
Ticket {{ $loop->iteration }}: {{ $ticket->code }}
@endforeach

@props(['tickets', 'numbered' => true, 'holders' => false])
@foreach ($tickets as $ticket)
Ticket{{ $numbered ? ' '.$loop->iteration : '' }}: {{ $ticket->code }}@if ($holders) ({{ $ticket->holder_name ?: 'no name yet' }})@endif

@endforeach

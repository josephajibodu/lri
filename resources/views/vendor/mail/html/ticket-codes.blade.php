@props(['tickets', 'accent' => '#FEB203', 'numbered' => true, 'holders' => false])
<table class="tickets" width="100%" cellpadding="0" cellspacing="0" role="presentation">
@foreach ($tickets as $ticket)
<tr>
<td class="tickets-row">
<table class="ticket" width="100%" cellpadding="0" cellspacing="0" role="presentation" bgcolor="#0B0A10">
<tr>
<td class="ticket-label" width="104" style="border-right: 1px dashed {{ $accent }}; color: {{ $accent }};">Ticket{{ $numbered ? ' '.$loop->iteration : '' }}</td>
<td class="ticket-code" align="center">{{ $ticket->code }}@if ($holders)<br><span class="ticket-holder">{{ $ticket->holder_name ?: 'No name yet' }}</span>@endif</td>
</tr>
</table>
</td>
</tr>
@endforeach
</table>

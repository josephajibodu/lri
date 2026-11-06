@props(['tickets', 'accent' => '#FFB000', 'numbered' => true, 'holders' => false])
<table class="tickets" width="100%" cellpadding="0" cellspacing="0" role="presentation">
@foreach ($tickets as $ticket)
<tr>
<td class="tickets-row">
<table class="ticket" width="100%" cellpadding="0" cellspacing="0" role="presentation" bgcolor="#17151F">
<tr>
<td class="ticket-label" width="96" style="border-right: 1px dashed {{ $accent }}; color: {{ $accent }};">Ticket{{ $numbered ? ' '.$loop->iteration : '' }}</td>
<td class="ticket-code" align="left">{{ $ticket->code }}@if ($holders)<br><span class="ticket-holder">{{ App\Support\PersonName::forEmail($ticket->holder_name) ?: 'No name yet' }}</span>@endif</td>
</tr>
</table>
</td>
</tr>
@endforeach
</table>

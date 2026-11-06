@props(['eventName', 'details' => [], 'ticket' => null, 'message' => null, 'label' => 'Admit one', 'accent' => '#FFB000', 'gradient' => null])
@php
$fields = collect($details)->merge($ticket ? ['Name' => App\Support\PersonName::forEmail($ticket->holder_name)] : [])->filter();
$qrCode = $ticket ? App\Support\TicketQrCode::png($ticket) : null;
@endphp
<table class="pass" width="100%" cellpadding="0" cellspacing="0" role="presentation" bgcolor="#FFFFFF">
<tr>
<td class="pass-strip" bgcolor="{{ $gradient ? $accent : '#17151F' }}" @if ($gradient) style="background-color: {{ $accent }}; background-image: {{ $gradient }};" @endif>
<p class="pass-label" style="color: {{ $gradient ? '#FFFFFF' : $accent }};">{{ $label }}</p>
<p class="pass-event">{{ Str::replaceLast(' ', "\u{00A0}", $eventName) }}</p>
</td>
</tr>
@if ($fields->isNotEmpty())
<tr>
<td class="pass-body">
<p class="pass-field-label">{{ $fields->keys()->first() }}</p>
<p class="pass-field-lead">{{ $fields->first() }}</p>
@foreach ($fields->slice(1)->chunk(2) as $row)
<table width="100%" cellpadding="0" cellspacing="0" role="presentation">
<tr>
@foreach ($row as $name => $value)
<td class="pass-field" width="50%" valign="top">
<p class="pass-field-label">{{ $name }}</p>
<p class="pass-field-value">{{ $value }}</p>
</td>
@endforeach
</tr>
</table>
@endforeach
</td>
</tr>
@endif
@if ($ticket)
<tr>
<td class="pass-tear">
<table width="100%" cellpadding="0" cellspacing="0" role="presentation">
<tr>
<td class="pass-notch pass-notch-left" width="13" height="26" bgcolor="#0B0A10"></td>
<td class="pass-perforation" valign="middle"><div class="pass-perforation-line"></div></td>
<td class="pass-notch pass-notch-right" width="13" height="26" bgcolor="#0B0A10"></td>
</tr>
</table>
</td>
</tr>
<tr>
<td class="pass-stub" align="center">
<img src="{{ $message ? $message->embedData($qrCode, 'ticket-'.$ticket->code.'.png', 'image/png') : 'data:image/png;base64,'.base64_encode($qrCode) }}" width="220" height="220" alt="QR code for ticket {{ $ticket->code }}" class="pass-qr">
<p class="pass-code">{{ $ticket->code }}</p>
<p class="pass-hint">Show this at the desk to collect your headset.<br>If it won’t scan, they can type the code.</p>
</td>
</tr>
@endif
</table>

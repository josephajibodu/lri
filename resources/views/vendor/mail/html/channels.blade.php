@props(['channels', 'accent' => '#FFB000'])
@php($tones = ['coral' => '#FF6B57', 'green' => '#39E58C', 'sky' => '#4FB6FF', 'pink' => '#FF7EC4', 'yellow' => '#FFB000'])
<p class="channels-title">{{ count($channels) }} channels. {{ count($channels) }} vibes.</p>
<p class="channels-text">Your headset, your call.</p>
<table class="channels" width="100%" cellpadding="0" cellspacing="0" role="presentation">
<tr>
@foreach ($channels as $channel)
@php($tone = $tones[$channel['tone'] ?? ''] ?? $accent)
@unless ($loop->first)
<td class="channel-gap" width="8"></td>
@endunless
<td class="channel" width="{{ floor(100 / count($channels)) }}%" align="center" bgcolor="#17151F" style="border-top: 3px solid {{ $tone }}; color: {{ $tone }};">Ch {{ $loop->iteration }}</td>
@endforeach
</tr>
</table>

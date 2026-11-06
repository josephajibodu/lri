@props(['url', 'accent' => '#FFB000', 'gradient' => null])
<table class="action" width="100%" cellpadding="0" cellspacing="0" role="presentation">
<tr>
<td class="button-cell" align="center" bgcolor="{{ $accent }}" style="background-color: {{ $accent }};@if ($gradient) background-image: {{ $gradient }};@endif">
<a href="{{ $url }}" class="button" target="_blank" rel="noopener" style="color: {{ $gradient ? '#FFFFFF' : '#0B0A10' }};">{!! $slot !!}</a>
</td>
</tr>
</table>

@props(['url'])
<table class="action" width="100%" cellpadding="0" cellspacing="0" role="presentation">
<tr>
<td class="button-cell" align="center" bgcolor="#0B0A10">
<a href="{{ $url }}" class="button" target="_blank" rel="noopener">{!! $slot !!}</a>
</td>
</tr>
</table>

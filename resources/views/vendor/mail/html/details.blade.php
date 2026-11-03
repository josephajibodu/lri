@props(['rows'])
<table class="details" width="100%" cellpadding="0" cellspacing="0" role="presentation">
@foreach ($rows as $label => $value)
<tr>
<td class="details-label" width="72" valign="top">{{ $label }}</td>
<td class="details-value" valign="top">{{ $value }}</td>
</tr>
@endforeach
</table>

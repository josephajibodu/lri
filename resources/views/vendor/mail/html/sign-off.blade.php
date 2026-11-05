@props(['tagline' => null])
<table class="sign-off" width="100%" cellpadding="0" cellspacing="0" role="presentation">
<tr>
<td class="sign-off-cell">
@if ($tagline)
<p class="sign-off-tagline">{{ $tagline }}</p>
@endif
<p class="sign-off-text">See you on the run,<br>{{ config('lri.site.name') }}</p>
</td>
</tr>
</table>

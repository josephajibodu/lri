@props(['url'])
<tr>
<td class="header">
<a href="{{ $url }}" style="display: inline-block;">
<img src="{{ asset('images/lri-logo-email.png') }}" class="logo" width="88" height="105" alt="{!! trim($slot) !!}">
</a>
</td>
</tr>

@props(['url'])
<tr>
<td class="header" align="center">
<a href="{{ $url }}" style="display: inline-block;">
<img src="{{ \App\Support\SiteAsset::url('images/lri-logo-on-dark.png') }}" class="logo" width="88" height="105" alt="{!! trim($slot) !!}">
</a>
</td>
</tr>

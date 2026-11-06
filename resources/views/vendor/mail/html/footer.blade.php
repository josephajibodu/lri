<tr>
<td>
<table class="footer" align="center" width="600" cellpadding="0" cellspacing="0" role="presentation">
<tr>
<td class="content-cell" align="center">
<a href="{{ config('app.url') }}" style="display: inline-block;">
<img src="{{ \App\Support\SiteAsset::url('images/lri-logo-on-dark.png') }}" class="footer-logo" width="64" height="76" alt="{{ config('lri.site.name') }}">
</a>
{{ Illuminate\Mail\Markdown::parse($slot) }}
</td>
</tr>
</table>
</td>
</tr>

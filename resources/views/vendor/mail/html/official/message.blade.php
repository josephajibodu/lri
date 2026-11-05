@props(['preheader' => null])
<!DOCTYPE html PUBLIC "-//W3C//DTD XHTML 1.0 Transitional//EN" "http://www.w3.org/TR/xhtml1/DTD/xhtml1-transitional.dtd">
<html xmlns="http://www.w3.org/1999/xhtml" lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
<title>{{ config('lri.site.name') }}</title>
<meta name="viewport" content="width=device-width, initial-scale=1.0" />
<meta http-equiv="Content-Type" content="text/html; charset=UTF-8" />
<meta name="color-scheme" content="light">
<meta name="supported-color-schemes" content="light">
<style>
@media only screen and (max-width: 620px) {
.wrapper-cell {
padding: 16px 12px 24px !important;
}

.card-header {
padding: 28px 24px 0 !important;
}

.card-body {
padding: 20px 24px 28px !important;
}

h1 {
font-size: 24px !important;
}
}
</style>
<style>
a[x-apple-data-detectors] {
color: inherit !important;
font: inherit !important;
text-decoration: none !important;
}
</style>
</head>
<body bgcolor="#F3F2EE">
@if ($preheader)
<div class="preheader">{{ $preheader }}</div>
@endif

<table class="wrapper" width="100%" cellpadding="0" cellspacing="0" role="presentation" bgcolor="#F3F2EE">
<tr>
<td class="wrapper-cell" align="center">
<!--[if mso]><table align="center" width="560" cellpadding="0" cellspacing="0" role="presentation"><tr><td><![endif]-->
<table class="card" align="center" width="100%" cellpadding="0" cellspacing="0" role="presentation" bgcolor="#FFFFFF">
<tr>
<td class="brand-stripe" bgcolor="#FEB203" height="5">&nbsp;</td>
</tr>
<tr>
<td class="card-header">
<a href="{{ config('app.url') }}" style="display: inline-block;">
<img src="{{ asset('images/lri-logo-email.png') }}" class="logo" width="62" height="74" alt="{{ config('lri.site.name') }}">
</a>
</td>
</tr>
<tr>
<td class="card-body">
{!! Illuminate\Mail\Markdown::parse($slot) !!}
</td>
</tr>
</table>
<table class="footer" align="center" width="100%" cellpadding="0" cellspacing="0" role="presentation">
<tr>
<td class="footer-cell" align="center">
<p><strong>{{ config('lri.site.name') }}</strong></p>
<p>{{ config('lri.site.locality') }}, {{ config('lri.site.region') }} · <a href="{{ config('app.url') }}">{{ Str::after(config('app.url'), '://') }}</a></p>
</td>
</tr>
</table>
<!--[if mso]></td></tr></table><![endif]-->
</td>
</tr>
</table>
</body>
</html>

@props(['preheader' => null, 'bannerUrl' => null, 'bannerAlt' => null])
<!DOCTYPE html PUBLIC "-//W3C//DTD XHTML 1.0 Transitional//EN" "http://www.w3.org/TR/xhtml1/DTD/xhtml1-transitional.dtd">
<html xmlns="http://www.w3.org/1999/xhtml" lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
<title>{{ config('lri.site.name') }}</title>
<meta name="viewport" content="width=device-width, initial-scale=1.0" />
<meta http-equiv="Content-Type" content="text/html; charset=UTF-8" />
<meta name="color-scheme" content="light">
<meta name="supported-color-schemes" content="light">
<style>
@media only screen and (max-width: 600px) {
.wrapper-cell {
padding: 16px 12px !important;
}

.inner-body,
.footer {
width: 100% !important;
}

.content-cell {
padding: 28px 22px !important;
}

h1 {
font-size: 26px !important;
}
}
</style>
{!! $head ?? '' !!}
</head>
<body>
@if ($preheader)
<div class="preheader">{{ $preheader }}</div>
@endif

<table class="wrapper" width="100%" cellpadding="0" cellspacing="0" role="presentation">
<tr>
<td class="wrapper-cell" align="center">
<table class="content" width="100%" cellpadding="0" cellspacing="0" role="presentation">
<tr>
<td>
<table class="inner-body" align="center" width="570" cellpadding="0" cellspacing="0" role="presentation">
{!! $header ?? '' !!}
@if ($bannerUrl)
<tr>
<td class="banner">
<img src="{{ $bannerUrl }}" width="570" alt="{{ $bannerAlt }}">
</td>
</tr>
@endif

<!-- Body content -->
<tr>
<td class="content-cell">
{!! Illuminate\Mail\Markdown::parse($slot) !!}

{!! $subcopy ?? '' !!}
</td>
</tr>
</table>
</td>
</tr>

{!! $footer ?? '' !!}
</table>
</td>
</tr>
</table>
</body>
</html>

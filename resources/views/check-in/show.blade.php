{{-- The check-in scanner (CheckInController), for staff on the gate. A phone page of its own, without the site's header and footer, so the camera fills the screen. resources/js/check-in.js does the scanning. --}}
<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover">
        <meta name="robots" content="noindex, nofollow">
        <meta name="csrf-token" content="{{ csrf_token() }}">
        <meta name="theme-color" content="#1C1917">

        <title>Check-in · {{ config('lri.site.name') }}</title>

        <link rel="manifest" href="{{ route('check-in.manifest') }}">
        <meta name="mobile-web-app-capable" content="yes">
        <meta name="apple-mobile-web-app-capable" content="yes">
        <meta name="apple-mobile-web-app-title" content="Check-in">
        <meta name="apple-mobile-web-app-status-bar-style" content="black-translucent">
        <link rel="icon" href="{{ \App\Support\SiteAsset::url('favicon.svg') }}" type="image/svg+xml">
        <link rel="apple-touch-icon" href="{{ \App\Support\SiteAsset::url('apple-touch-icon.png') }}">

        @vite(['resources/css/app.css', 'resources/js/check-in.js'])
    </head>
    <body class="ci">
        <div
            class="ci-page"
            data-check-in
            data-scan-url="{{ route('check-in.scans') }}"
            data-start-url="{{ route('check-in.start') }}"
            data-service-worker-url="{{ route('check-in.service-worker') }}"
            data-scope="{{ route('check-in', absolute: false) }}"
        >
            <header class="ci-bar">
                <div>
                    <h1 class="ci-title">Check-in</h1>
                    <p class="ci-count"><span data-let-in>{{ number_format($counts['let_in']) }}</span> of <span data-tickets>{{ number_format($counts['tickets']) }}</span> let in</p>
                </div>
                <div class="ci-bar-end">
                    <button type="button" class="ci-pill" data-install hidden>Install</button>
                    <a class="ci-pill" href="{{ url('/admin') }}">Admin</a>
                </div>
            </header>

            @unless ($checkInOpen)
                <p class="ci-practice"><strong>Practice mode.</strong> Check-in hasn’t opened yet, so tickets are checked but not marked as used.</p>
            @endunless

            <main class="ci-camera">
                <video class="ci-video" data-video playsinline muted aria-hidden="true"></video>
                <div class="ci-frame" aria-hidden="true"></div>
                <div class="ci-start" data-start>
                    <button type="button" class="ci-start-button" data-start-button>Start scanning</button>
                    <p class="ci-camera-note" data-camera-note role="status">Point the camera at the QR code on their ticket.</p>
                </div>
            </main>

            <form class="ci-type" data-type-form>
                <label class="ci-type-label" for="ci-code">Won’t scan? Type the code under the QR code.</label>
                <div class="ci-type-row">
                    <input id="ci-code" class="ci-type-input" name="code" type="text" inputmode="text" autocomplete="off" autocapitalize="characters" autocorrect="off" spellcheck="false" placeholder="7K3Q-9PXM" maxlength="20" required>
                    <button type="submit" class="ci-type-button">Check</button>
                </div>
                <p class="ci-tip" data-ios-tip hidden>To open this like an app, tap Share, then <strong>Add to Home Screen</strong>.</p>
            </form>
        </div>

        <section class="ci-result" data-result data-state="checking" hidden role="alert" aria-live="assertive">
            <div class="ci-result-body">
                <p class="ci-result-heading" data-result-heading></p>
                <p class="ci-result-name" data-result-name></p>
                <p class="ci-result-code" data-result-code></p>
                <p class="ci-result-detail" data-result-detail></p>
            </div>
            <button type="button" class="ci-result-button" data-result-button>Scan next</button>
        </section>
    </body>
</html>

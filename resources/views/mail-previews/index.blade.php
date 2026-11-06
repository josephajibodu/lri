@php
    $icons = [
        'desktop' => '<rect x="3" y="4" width="18" height="12" rx="2"/><path d="M8 20h8M12 16v4"/>',
        'tablet' => '<rect x="5" y="3" width="14" height="18" rx="2"/><path d="M11 18h2"/>',
        'mobile' => '<rect x="7" y="3" width="10" height="18" rx="2"/><path d="M11 18h2"/>',
    ];
    $screen = $devices[$device];
@endphp

<x-layouts.app title="Email previews · Let’s Run Ibadan" robots="noindex, nofollow">
    <x-slot:head>
        {{-- Kept here because this page is never routed in production, so its styles stay out of the site's stylesheet. --}}
        <style>
            body { overflow: hidden; }
            .mp { display: flex; flex-direction: column; height: 100dvh; background: var(--bg); color: var(--ink); font-family: var(--sans); }
            .mp a { text-decoration: none; }

            .mp-bar { display: flex; flex-direction: column; gap: 12px; padding: 12px 20px; background: var(--card); border-bottom: 1px solid var(--line); }
            .mp-bar-row { display: flex; flex-wrap: wrap; align-items: center; justify-content: space-between; gap: 12px; }
            .mp-brand { display: flex; align-items: center; gap: 10px; min-width: 0; }
            .mp-brand img { flex: none; display: block; }
            .mp-brand h1 { font-size: 1.05rem; font-weight: 700; line-height: 1.2; letter-spacing: -.01em; }
            .mp-brand p { margin: 2px 0 0; color: var(--muted); font-size: .78rem; line-height: 1.3; }

            .mp-devices { display: flex; gap: 2px; padding: 3px; background: var(--paper); border: 1px solid var(--line); border-radius: 12px; }
            .mp-device-link { display: flex; align-items: center; gap: 7px; padding: 7px 12px; border-radius: 9px; color: var(--muted); font-size: .86rem; font-weight: 600; line-height: 1; white-space: nowrap; transition: color .12s, background-color .12s; }
            .mp-device-link:hover { color: var(--ink); background: var(--card); }
            .mp-device-link[aria-current] { background: var(--yellow); color: var(--on-color); }
            .mp-device-link svg { flex: none; width: 16px; height: 16px; fill: none; stroke: currentColor; stroke-width: 2; stroke-linecap: round; stroke-linejoin: round; }
            .mp-device-size { font-family: var(--mono); font-size: .68rem; font-weight: 500; opacity: .7; }

            .mp-tabs { display: flex; gap: 6px; margin: 0 -20px; padding: 0 20px 2px; overflow-x: auto; scrollbar-width: none; }
            .mp-tabs::-webkit-scrollbar { display: none; }
            .mp-tab { flex: none; padding: 8px 14px; border: 1px solid var(--line); border-radius: 999px; background: var(--card); color: var(--ink); font-size: .88rem; font-weight: 600; line-height: 1.1; transition: border-color .12s; }
            .mp-tab:hover { border-color: var(--ink); }
            .mp-tab[aria-current] { background: var(--ink); border-color: var(--ink); color: #FFFFFF; }

            .mp-info { display: flex; flex-wrap: wrap; align-items: baseline; gap: 2px 16px; padding: 10px 20px; font-size: .86rem; line-height: 1.4; }
            .mp-subject { margin: 0; min-width: 0; }
            .mp-subject span { margin-right: 6px; color: var(--muted); font-family: var(--mono); font-size: .68rem; letter-spacing: .08em; text-transform: uppercase; }
            .mp-about { margin: 0; color: var(--muted); }
            .mp-info-end { display: flex; align-items: baseline; gap: 14px; margin-left: auto; }
            .mp-scale { color: var(--muted); font-family: var(--mono); font-size: .72rem; white-space: nowrap; }
            .mp-open { font-weight: 600; white-space: nowrap; border-bottom: 2px solid var(--yellow); }

            .mp-stage { position: relative; flex: 1; min-height: 0; overflow: hidden; }
            .mp-screen { display: block; width: 100%; height: 100%; border: 0; background: #0B0A10; }

            .mp-device { position: absolute; display: flex; flex-direction: column; box-sizing: border-box; background: #1C1917; }

            .mp-device-desktop { inset: 0 16px 16px; max-width: 1280px; margin-inline: auto; overflow: hidden; border-radius: 12px; border: 1px solid #1C1917; box-shadow: 4px 4px 0 rgba(28, 25, 23, .16); }
            .mp-window-bar { flex: none; display: flex; gap: 6px; padding: 10px 12px; }
            .mp-window-bar i { width: 10px; height: 10px; border-radius: 50%; background: #57534E; }

            .mp-device-framed { top: 2px; left: 50%; width: calc(var(--width) + var(--bezel) * 2); height: calc((100% - 18px) / var(--scale, 1)); max-height: calc(var(--height) + var(--bezel-top) + var(--bezel)); padding: var(--bezel-top) var(--bezel) var(--bezel); border-radius: var(--corner); box-shadow: 6px 6px 0 rgba(28, 25, 23, .16); transform: translateX(-50%) scale(var(--scale, 1)); transform-origin: top center; }
            .mp-device-framed .mp-screen { border-radius: calc(var(--corner) - var(--bezel)); }
            .mp-device-tablet { --bezel: 14px; --bezel-top: 14px; --corner: 28px; }
            .mp-device-mobile { --bezel: 10px; --bezel-top: 28px; --corner: 44px; }
            .mp-device-mobile::before { content: ""; position: absolute; top: 10px; left: 50%; width: 72px; height: 8px; border-radius: 8px; background: #44403B; transform: translateX(-50%); }

            @media (max-width: 640px) {
                .mp-bar { padding: 10px 14px; gap: 10px; }
                .mp-brand p, .mp-about, .mp-device-size { display: none; }
                .mp-devices { width: 100%; }
                .mp-device-link { flex: 1; justify-content: center; }
                .mp-tabs { margin: 0 -14px; padding: 0 14px 2px; }
                .mp-info { padding: 8px 14px; }
                .mp-device-desktop { inset: 0 8px 8px; }
            }
        </style>
    </x-slot:head>

    <main class="mp">
        <header class="mp-bar">
            <div class="mp-bar-row">
                <div class="mp-brand">
                    <img src="{{ \App\Support\SiteAsset::url('favicon.svg') }}" width="34" height="34" alt="">
                    <div>
                        <h1>Email previews</h1>
                        <p>Local only. Sample data, nothing is saved or sent.</p>
                    </div>
                </div>

                <nav class="mp-devices" aria-label="Screen size">
                    @foreach ($devices as $key => $option)
                        <a class="mp-device-link" href="{{ route('mail-previews.index', ['preview' => $selected, 'device' => $key]) }}" @if ($key === $device) aria-current="true" @endif>
                            <svg viewBox="0 0 24 24" aria-hidden="true">{!! $icons[$key] !!}</svg>
                            {{ $option['name'] }}
                            @if ($option['width'])
                                <span class="mp-device-size">{{ $option['width'] }}</span>
                            @endif
                        </a>
                    @endforeach
                </nav>
            </div>

            <nav class="mp-tabs" aria-label="Emails">
                @foreach ($previews as $key => $option)
                    <a class="mp-tab" href="{{ route('mail-previews.index', ['preview' => $key, 'device' => $device]) }}" @if ($key === $selected) aria-current="page" @endif>{{ $option['name'] }}</a>
                @endforeach
            </nav>
        </header>

        <section class="mp-info">
            <p class="mp-subject"><span>Subject</span><strong>{{ $previews[$selected]['subject'] }}</strong></p>
            <p class="mp-about">{{ $previews[$selected]['description'] }}</p>
            <div class="mp-info-end">
                <span class="mp-scale" data-scale-label hidden></span>
                <a class="mp-open" href="{{ route('mail-previews.show', $selected) }}" target="_blank">Open on its own</a>
            </div>
        </section>

        <div class="mp-stage" data-stage>
            @if ($screen['width'])
                <div class="mp-device mp-device-framed mp-device-{{ $device }}" style="--width: {{ $screen['width'] }}px; --height: {{ $screen['height'] }}px;" data-device data-width="{{ $screen['width'] }}">
                    <iframe class="mp-screen" src="{{ route('mail-previews.show', $selected) }}" title="{{ $previews[$selected]['name'] }}, {{ $screen['name'] }}"></iframe>
                </div>
            @else
                <div class="mp-device mp-device-desktop">
                    <div class="mp-window-bar" aria-hidden="true"><i></i><i></i><i></i></div>
                    <iframe class="mp-screen" src="{{ route('mail-previews.show', $selected) }}" title="{{ $previews[$selected]['name'] }}, {{ $screen['name'] }}"></iframe>
                </div>
            @endif
        </div>
    </main>

    @if ($screen['width'])
        {{-- Shrinks the whole device when the window is narrower than it. The email still lays out at the device's true width. --}}
        <script>
            (function () {
                var stage = document.querySelector('[data-stage]');
                var device = document.querySelector('[data-device]');
                var label = document.querySelector('[data-scale-label]');

                function fit() {
                    var scale = Math.min(1, (stage.clientWidth - 16) / device.offsetWidth);
                    device.style.setProperty('--scale', scale);
                    label.hidden = scale === 1;
                    label.textContent = 'Shown at ' + Math.round(scale * 100) + '%';
                }

                new ResizeObserver(fit).observe(stage);
                fit();
            })();
        </script>
    @endif
</x-layouts.app>

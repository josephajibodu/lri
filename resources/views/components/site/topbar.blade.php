@props(['nextRun', 'discoRun' => null])

@if ($discoRun)
    <div class="topbar topbar-disco">
        <span class="topbar-spark" style="--x: 6%; --i: 0" aria-hidden="true"></span>
        <span class="topbar-spark" style="--x: 93%; --i: 1" aria-hidden="true"></span>
        <div class="wrap topbar-row">
            <span class="topbar-eq" aria-hidden="true"><i></i><i></i><i></i><i></i></span>
            <span class="topbar-new">New</span>
            <a class="topbar-title" href="{{ route('disco-run') }}">Silent Disco Run</a>
            <span class="topbar-when">
                @if ($discoRun['starts_at'])
                    <span class="topbar-day">{{ $discoRun['starts_at']->format('D') }}</span> {{ $discoRun['starts_at']->format('j M') }}<span class="topbar-time"> · {{ $discoRun['starts_at']->format('g A') }}</span>
                @else
                    {{ $discoRun['date_window'] }}
                @endif
                @if ($discoRun['venue'])
                    <span class="topbar-venue">· {{ $discoRun['venue'] }}</span>
                @endif
            </span>
            @if ($discoRun['days_to_go'] !== null)
                <span class="topbar-left">{{ $discoRun['days_to_go'] === 0 ? 'Tonight' : $discoRun['days_to_go'].' '.Str::plural('day', $discoRun['days_to_go']).' to go' }}</span>
            @endif
            <a class="topbar-cta" href="{{ route('disco-run') }}#tickets"><span class="topbar-get">Get tickets</span><span class="topbar-short">Tickets</span> →</a>
        </div>
    </div>
@else
    <div class="topbar">
        <div class="wrap topbar-row">
            <span class="topbar-pill">Next run</span>
            <span>{{ $nextRun['starts_at']->format('D j M, g:i A') }} at {{ $nextRun['venue'] }}</span>
            <a href="#next">Details →</a>
        </div>
    </div>
@endif

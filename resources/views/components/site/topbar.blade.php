@props(['nextRun'])

<div class="topbar">
    <div class="wrap topbar-row">
        <span class="topbar-pill">Next run</span>
        <span>{{ $nextRun['starts_at']->format('D j M, g:i A') }} at {{ $nextRun['venue'] }}</span>
        <a href="#next">Details →</a>
    </div>
</div>

<header {{ $attributes->class('site-head') }}>
    <div class="wrap head-row">
        <a class="brand" href="{{ route('home') }}#top" aria-label="Let’s Run Ibadan, back to top">
            <x-logo-mark class="brand-logo" />
            <span class="brand-name">Let’s Run Ibadan</span>
        </a>
        <nav class="site-nav" aria-label="Sections">
            <a href="{{ route('home') }}#pace">Every pace</a>
            <a href="{{ route('home') }}#week">Schedule</a>
            <a href="{{ route('home') }}#tribes">Tribes</a>
            <a href="{{ route('home') }}#routes">Routes</a>
            <a href="{{ route('home') }}#events">Events</a>
            <a href="{{ route('home') }}#faq">FAQ</a>
            <a href="{{ route('founders') }}">Founders</a>
            <a href="{{ route('pace-setters') }}">Pace setters</a>
        </nav>
        @isset($cta)
            {{ $cta }}
        @else
            <a class="btn btn-yellow btn-sm" href="{{ route('home') }}#join">Join LRI</a>
        @endisset
    </div>
</header>

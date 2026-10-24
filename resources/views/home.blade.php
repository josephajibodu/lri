<x-layouts.app>
    <x-site.topbar :next-run="$nextRun" />
    <x-site.header />

    <main>
        @include('home.hero')
        @include('home.next-run', ['nextRun' => $nextRun])
        @include('home.marquee')
        @include('home.pace')
        @include('home.schedule')
        @include('home.tribes')
        @include('home.routes')
        @include('home.events')
        @include('home.first-run')
        @include('home.community')
        @include('home.join')
        @include('home.faq')
    </main>

    <x-site.footer />
</x-layouts.app>

<x-layouts.app title="Founders · Let’s Run Ibadan" description="Meet the people who started Let’s Run Ibadan, the free Saturday run club, and the story from the first run to four tribes." :canonical="route('founders')">
    <x-site.header />

    <main>
        @include('founders.hero')
        @include('founders.marquee')
        @include('founders.roster')
        @include('founders.profiles')
        @include('founders.story')
        @include('founders.join')
    </main>

    <x-site.footer />
</x-layouts.app>

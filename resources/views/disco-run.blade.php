<x-layouts.app title="Silent Disco Run · Let’s Run Ibadan" description="Headsets on, run and vibe, then rave. A night run through Ibadan on three live DJ channels, with a rave at the finish." :canonical="route('disco-run')">
    <x-site.header />

    <main class="dr dr-tone-{{ $discoRun['channels'][0]['tone'] }}" id="disco">
        @include('disco-run.hero')
        @include('disco-run.marquee')
        @include('disco-run.how')
        @include('disco-run.channels')
        @include('disco-run.ticket')
        @include('disco-run.faq')
    </main>

    <x-site.footer />
</x-layouts.app>

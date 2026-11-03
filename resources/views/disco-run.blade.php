<x-layouts.app title="Silent Disco Run · Let’s Run Ibadan" description="Headsets on, run and vibe, then rave. A night run through Ibadan on three live DJ channels, with a rave at the finish." :canonical="route('disco-run')" image="images/disco-run/og-image.jpg" image-alt="Let’s Run Ibadan presents the Silent Disco Run. Friday 11 December, 6 PM, Bodija, Ibadan. Tickets ₦10,000 with silent disco headphones.">
    <x-site.header />

    <main class="dr dr-tone-{{ $discoRun['channels'][0]['tone'] }}" id="disco">
        @include('disco-run.hero')
        @include('disco-run.marquee')
        @include('disco-run.how')
        @include('disco-run.channels')
        @include('disco-run.ticket')
        @include('disco-run.share')
        @include('disco-run.faq')
    </main>

    <x-site.footer />
</x-layouts.app>

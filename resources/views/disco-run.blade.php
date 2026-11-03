@php($tone = $discoRun['channels'][0]['tone'])
<x-layouts.app title="Silent Disco Run · Let’s Run Ibadan" description="Headsets on, run and vibe, then rave. A night run through Ibadan on three live DJ channels, with a rave at the finish." :canonical="route('disco-run')" image="images/disco-run/og-image.jpg" image-alt="Let’s Run Ibadan presents the Silent Disco Run. Friday 11 December, 6 PM, Bodija, Ibadan. Tickets ₦10,000 with silent disco headphones." theme-color="#0B0A10">
    <x-site.header class="site-head-night dr-tone-{{ $tone }}">
        @if ($ticketing['state'] === 'on_sale')
            <x-slot:cta>
                <a class="btn dr-btn btn-sm" href="#tickets">Get your ticket</a>
            </x-slot>
        @endif
    </x-site.header>

    <main class="dr dr-tone-{{ $tone }}" id="disco">
        @include('disco-run.hero')
        @include('disco-run.marquee')
        @include('disco-run.how')
        @include('disco-run.channels')

        <div class="dr-day dr-tone-{{ $tone }}">
            @include('disco-run.ticket')
            @include('disco-run.share')
            @include('disco-run.faq')
        </div>
    </main>

    <x-site.footer />
</x-layouts.app>

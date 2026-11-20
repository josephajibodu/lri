@php($tone = $discoRun['channels'][0]['tone'])
<x-layouts.app title="Silent Disco Run · Let’s Run Ibadan" description="Headsets on. Walk, dance and vibe through Ibadan at night on three live DJ channels, then rave at the finish. No running needed." :canonical="route('disco-run')" image="images/disco-run/og-image.jpg" image-alt="Let’s Run Ibadan presents the Silent Disco Run. Friday 11 December, 6 PM, Bodija, Ibadan. Tickets ₦10,000 with silent disco headphones." theme-color="#EEECE7">
    @if ($ticketing['sale'])
        <x-site.sale-bar :sale="$ticketing['sale']" :event="$discoRun['title']" href="#tickets" />
    @endif
    <x-site.header class="site-head-event dr-tone-{{ $tone }}">
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

        <div class="dr-buy dr-tone-{{ $tone }}">
            @include('disco-run.ticket')
            @include('disco-run.share')
            @include('disco-run.faq')
        </div>
    </main>

    <x-site.footer />
</x-layouts.app>

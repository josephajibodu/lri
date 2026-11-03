<x-layouts.app title="Pace Setters · Let’s Run Ibadan" description="Meet the pace setters of Let’s Run Ibadan: the club leaders and the leads of the Akobo, Bodija, UI and Oluyole tribes." :canonical="route('pace-setters')">
    <x-site.header />

    <main>
        @include('pace-setters.hero')
        @include('pace-setters.lanes')
        @include('pace-setters.join')
    </main>

    <x-site.footer />
</x-layouts.app>

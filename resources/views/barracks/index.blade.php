<x-layouts.app title="Barracks registrations · Let’s Run Ibadan" robots="noindex">
    <x-site.header />

    <main class="sec bday">
        <div class="wrap">
            <div class="bday-list-head">
                <div class="sec-head">
                    <p class="label">Akobo Barracks</p>
                    <h1 class="bday-title">Registrations <span class="wm-hl">{{ $registrations->count() }}</span></h1>
                    <p class="sec-lede">
                        {{ trans_choice('{0} Nobody has registered yet.|{1} One runner has registered.|[2,*] :count runners have registered.', $registrations->count()) }}
                        Share <a href="{{ route('barracks.create') }}">{{ route('barracks.create') }}</a> in the Akobo group so more members can register.
                    </p>
                </div>

                @if ($registrations->isNotEmpty())
                    <a class="btn btn-sm" href="{{ route('barracks.print') }}" target="_blank" rel="noopener">Print all forms</a>
                @endif
            </div>

            <ol class="bday-list">
                @forelse ($registrations as $registration)
                    <li class="bday-person">
                        <img class="barracks-passport" src="{{ $registration->passportUrl() }}" alt="Passport photograph of {{ $registration->full_name }}" loading="lazy">

                        <div class="bday-person-body">
                            <h2>{{ $registration->full_name }}</h2>
                            <p class="bday-meta">
                                {{ collect([
                                    $registration->phone,
                                    $registration->occupation,
                                    $registration->lga.', '.$registration->state_of_origin,
                                    'Registered '.$registration->created_at->format('j M Y'),
                                ])->join(' · ') }}
                            </p>
                            <p>{{ $registration->residential_address }}</p>
                            <p><a href="{{ route('barracks.print', $registration) }}" target="_blank" rel="noopener">Print this form</a></p>
                        </div>
                    </li>
                @empty
                    <li class="bday-empty">Nobody has registered yet.</li>
                @endforelse
            </ol>
        </div>
    </main>

    <x-site.footer />
</x-layouts.app>

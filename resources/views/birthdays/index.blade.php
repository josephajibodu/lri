<x-layouts.app :title="$monthName.' birthdays · Let’s Run Ibadan'" robots="noindex">
    <x-site.header />

    <main class="sec bday">
        <div class="wrap">
            <div class="bday-list-head">
                <div class="sec-head">
                    <p class="label">Birthdays to celebrate</p>
                    <h1 class="bday-title">{{ $monthName }} <span class="wm-hl">{{ $year }}</span></h1>
                    <p class="sec-lede">
                        {{ trans_choice('{0} No birthdays yet.|{1} One runner to celebrate.|[2,*] :count runners to celebrate.', $birthdays->count()) }}
                        Share <a href="{{ route('birthdays.create') }}">{{ route('birthdays.create') }}</a> so more members can add theirs.
                    </p>
                </div>

                <form class="bday-month" method="GET" action="{{ route('birthdays.index') }}">
                    <label class="field-label" for="month">Month</label>
                    <select class="input" id="month" name="month">
                        @foreach ($months as $monthNumber => $name)
                            <option value="{{ $monthNumber }}" @selected($month === $monthNumber)>{{ $name }}</option>
                        @endforeach
                    </select>
                    <button class="btn btn-sm" type="submit">Show</button>
                </form>
            </div>

            <ol class="bday-list">
                @forelse ($birthdays as $birthday)
                    @php($celebrationDate = $birthday->celebrationDate($year))
                    <li class="bday-person">
                        <p class="bday-date">
                            <span class="bday-date-day">{{ $celebrationDate->day }}</span>
                            <span class="bday-date-week">{{ $celebrationDate->format('D') }}</span>
                        </p>

                        <div class="bday-person-body">
                            <h2>
                                {{ $birthday->display_name }}
                                @if ($birthday->turningAge($year))
                                    <span class="tile-tag">Turning {{ $birthday->turningAge($year) }}</span>
                                @endif
                            </h2>
                            <p class="bday-meta">
                                {{ collect([
                                    $birthday->preferred_name ? $birthday->full_name : null,
                                    $birthday->tribe,
                                    $birthday->instagram_handle ? '@'.$birthday->instagram_handle : null,
                                    $birthday->email,
                                    $birthday->phone,
                                ])->filter()->join(' · ') }}
                            </p>

                            @if ($birthday->notes)
                                <p class="bday-notes"><strong>Notes:</strong> {{ $birthday->notes }}</p>
                            @endif

                            @if ($birthday->photo_paths)
                                <div class="bday-photos">
                                    @foreach ($birthday->photoUrls() as $photoUrl)
                                        <a href="{{ $photoUrl }}" target="_blank" rel="noopener">
                                            <img src="{{ $photoUrl }}" alt="Photo of {{ $birthday->display_name }}" loading="lazy">
                                        </a>
                                    @endforeach
                                </div>
                            @endif
                        </div>
                    </li>
                @empty
                    <li class="bday-empty">Nobody has shared a {{ $monthName }} birthday yet.</li>
                @endforelse
            </ol>
        </div>
    </main>

    <x-site.footer />
</x-layouts.app>

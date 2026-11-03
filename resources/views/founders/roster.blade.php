<section class="sec sec-paper" id="start-list">
    <div class="wrap">
        <div class="sec-head">
            <p class="label">Start list</p>
            <h2>Meet the <em>founders.</em></h2>
            <p class="sec-lede">The first names on the LRI start list. Pick one to read their story.</p>
        </div>

        <ol class="fd-roster" id="fd-roster">
            @foreach ($people as $person)
                <li>
                    <a class="fd-row" href="#{{ $person['anchor'] }}" data-bib="{{ $person['bib'] }}">
                        <span class="fd-row-bib">{{ $person['bib'] }}</span>
                        <span class="fd-row-name">{{ $person['name'] }}</span>
                        <span class="fd-row-meta">{{ $person['role'] }}{{ $person['tribe'] ? ' · '.$person['tribe'] : '' }}</span>
                        <span class="fd-row-go" aria-hidden="true">↘</span>
                    </a>
                </li>
            @endforeach
        </ol>

        <div class="fd-peek" id="fd-peek" aria-hidden="true">
            @foreach ($people as $person)
                <x-founder-portrait :person="$person" class="fd-peek-card" data-bib="{{ $person['bib'] }}" />
            @endforeach
        </div>
    </div>
</section>

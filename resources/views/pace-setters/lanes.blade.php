<section class="sec sec-paper" id="lanes">
    <div class="wrap">
        <div class="sec-head">
            <p class="label">Lane by lane</p>
            <h2>Find your <em>pacer.</em></h2>
            <p class="sec-lede">Every tribe has its own leaders, and a club-wide crew ties the four together. Pick a lane, or see everyone at once.</p>
        </div>

        <div class="ps-filter" id="ps-filter" role="group" aria-label="Show pace setters from" hidden>
            <button class="ps-chip" type="button" data-lane="all" aria-pressed="true">Everyone <span>{{ $people->count() }}</span></button>
            @foreach ($groups as $group)
                <button class="ps-chip" type="button" data-lane="{{ $group['slug'] }}" aria-pressed="false">{{ $group['name'] }} <span>{{ $group['people']->count() }}</span></button>
            @endforeach
        </div>

        <div class="ps-groups" id="ps-groups">
            @foreach ($groups as $group)
                <section class="ps-group" id="lane-{{ $group['slug'] }}" data-lane="{{ $group['slug'] }}" aria-labelledby="lane-{{ $group['slug'] }}-name">
                    <header class="ps-group-head">
                        <span class="ps-group-no"><small>Lane</small>{{ $group['lane'] }}</span>
                        <div class="ps-group-title">
                            <h3 class="ps-group-name" id="lane-{{ $group['slug'] }}-name">{{ $group['name'] }}</h3>
                            @if ($group['line'])
                                <p class="ps-group-line">{{ $group['line'] }}</p>
                            @endif
                        </div>
                        <span class="ps-group-count">{{ $group['people']->count() }} {{ Str::plural('pace setter', $group['people']->count()) }}</span>
                    </header>

                    <ul class="ps-cards">
                        @foreach ($group['people'] as $person)
                            <li class="ps-card fd-tone-{{ $person['tone'] }}" id="{{ $person['anchor'] }}">
                                <figure class="ps-photo">
                                    <x-founder-portrait :person="$person" :alt="'Portrait of '.$person['name']" />
                                    <figcaption class="ps-flag">{{ $person['role'] }}</figcaption>
                                </figure>
                                <div class="ps-card-body">
                                    <p class="ps-card-meta"><span>{{ $person['group'] }}</span></p>
                                    <h4 class="ps-name">{{ $person['name'] }}</h4>
                                    <p class="ps-bio">{{ $person['bio'] }}</p>
                                    @if ($person['instagram_url'])
                                        <a class="btn btn-sm ps-ig" href="{{ $person['instagram_url'] }}" target="_blank" rel="noopener">{{ '@'.$person['instagram'] }} ↗</a>
                                    @endif
                                </div>
                            </li>
                        @endforeach
                    </ul>
                </section>
            @endforeach
        </div>
    </div>
</section>

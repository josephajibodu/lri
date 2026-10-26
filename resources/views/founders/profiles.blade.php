<section class="sec fd-profiles-sec" aria-label="Founder profiles">
    <div class="wrap fd-profiles" id="fd-profiles">
        @foreach ($people as $person)
            <article class="fd-profile fd-tone-{{ $person['tone'] }}" id="{{ $person['anchor'] }}">
                <span class="fd-profile-no" aria-hidden="true">{{ $person['bib'] }}</span>

                <figure class="fd-photo">
                    <span class="fd-tape" aria-hidden="true"></span>
                    <x-founder-portrait :person="$person" :alt="'Portrait of '.$person['name']" />
                    <figcaption class="fd-photo-bib">No. {{ $person['bib'] }}</figcaption>
                </figure>

                <div class="fd-body">
                    <p class="fd-role">
                        <span class="fd-role-pill">{{ $person['role'] }}</span>
                        <span class="fd-tribe">{{ $person['tribe'] }} tribe</span>
                    </p>
                    <h3 class="fd-name">{{ $person['name'] }}</h3>
                    <blockquote class="fd-quote"><p>{{ $person['quote'] }}</p></blockquote>
                    <p class="fd-bio">{{ $person['bio'] }}</p>

                    @if ($person['facts'])
                        <dl class="fd-stats">
                            @foreach ($person['facts'] as $label => $value)
                                <div>
                                    <dt>{{ $label }}</dt>
                                    <dd>{{ $value }}</dd>
                                </div>
                            @endforeach
                        </dl>
                    @endif

                    @if ($person['links'])
                        <ul class="fd-links">
                            @foreach ($person['links'] as $label => $url)
                                <li><a class="btn btn-sm" href="{{ $url }}" target="_blank" rel="noopener">{{ $label }} ↗</a></li>
                            @endforeach
                        </ul>
                    @endif
                </div>
            </article>
        @endforeach
    </div>
</section>

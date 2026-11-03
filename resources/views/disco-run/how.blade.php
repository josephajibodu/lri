<section class="dr-sec" id="how">
    <div class="wrap">
        <div class="dr-head" data-reveal>
            <p class="dr-label">How the night goes</p>
            <h2>Four moves. <em>One</em> long party.</h2>
        </div>

        <ol class="dr-steps">
            @foreach ([
                ['Check in', 'Pick up your wireless LED headset at the start, pair it, and find your tribe in the crowd.', 'coral'],
                ['Pick a channel', 'Three DJs play live at the same time. Flip between them whenever you like. Your headset glows the colour of your channel.', 'green'],
                ['Run and vibe', 'We run '.$discoRun['distance'].' through Ibadan together at every pace, singing out loud to music nobody else can hear.', 'sky'],
                ['Rave after', 'The finish line turns into the dance floor. Headsets stay on and the DJs go head to head until the last song.', 'pink'],
            ] as [$name, $copy, $tone])
                <li class="dr-step dr-tone-{{ $tone }}" data-reveal style="--d: {{ $loop->index * .08 }}s">
                    <span class="dr-step-no">{{ Str::padLeft((string) $loop->iteration, 2, '0') }}</span>
                    <h3>{{ $name }}</h3>
                    <p>{{ $copy }}</p>
                </li>
            @endforeach
        </ol>
    </div>
</section>

<section class="dr-sec" id="faq">
    <div class="wrap dr-faq">
        <div class="dr-head" data-reveal>
            <p class="dr-label">Good to know</p>
            <h2>Questions, <em>answered.</em></h2>
        </div>

        <div class="dr-faq-list">
            @foreach ([
                ['Do I have to run?', 'No. It’s a party on the move, not a race. Walk it, dance it, or jog if you feel like it. Pacers hold the front and sweepers keep the back so nobody is left behind.'],
                ['Do I need my own headphones?', 'No. Your ticket comes with a wireless LED headset for the night. Hand it back at the end.'],
                ['Is it safe to be out at night with music on?', 'We move as one group on a planned route with marshals, and the volume is yours to set. Keep it low enough to hear the crew around you.'],
                ['When exactly is it?', $startsAt
                    ? $startsAt->format('l j F').' at '.$startsAt->format('g A').($discoRun['venue'] ? ' in '.$discoRun['venue'] : '').'. Updates go out on @letsrunibadan and on this page.'
                    : 'In the '.Str::lower($discoRun['date_window']).'. The exact date and venue go out on @letsrunibadan and on this page.'],
                ['Can I skip the '.$discoRun['distance'].' and just come for the rave?', 'The rave is where the '.$discoRun['distance'].' ends, so grab a ticket and come do both. Take it as slowly as you like.'],
            ] as [$question, $answer])
                <details class="dr-q" data-reveal>
                    <summary>{{ $question }}</summary>
                    <p>{{ $answer }}</p>
                </details>
            @endforeach
        </div>
    </div>
</section>

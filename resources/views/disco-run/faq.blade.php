<section class="dr-sec" id="faq">
    <div class="wrap dr-faq">
        <div class="dr-head" data-reveal>
            <p class="dr-label">Good to know</p>
            <h2>Questions, <em>answered.</em></h2>
        </div>

        <div class="dr-faq-list">
            @foreach ([
                ['Do I need my own headphones?', 'No. Your ticket comes with a wireless LED headset for the night. Hand it back at the end.'],
                ['What if I’m not a fast runner?', 'Everyone, every pace, one community. Jog it, walk it, dance it. Pacers hold the front and sweepers keep the back so nobody is left alone.'],
                ['Is it safe to run at night with music on?', 'We run as one group on a planned route with marshals, and the volume is yours to set. Keep it low enough to hear the crew around you.'],
                ['When exactly is it?', 'In the '.Str::lower($discoRun['date_window']).'. The exact date and venue go out on @letsrunibadan and on this page.'],
                ['Do I have to run to come to the rave?', 'The rave is for the runners, so grab a ticket and come do both.'],
            ] as [$question, $answer])
                <details class="dr-q" data-reveal>
                    <summary>{{ $question }}</summary>
                    <p>{{ $answer }}</p>
                </details>
            @endforeach
        </div>
    </div>
</section>

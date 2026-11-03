<section class="sec sec-paper" id="story">
    <div class="wrap fd-story">
        <div class="sec-head fd-story-head">
            <p class="label">The story so far</p>
            <h2>From one run to <em>five tribes.</em></h2>
            <p class="sec-lede">Every race has its splits. These are ours, from the first Saturday to the latest start line.</p>
        </div>

        <ol class="fd-splits" id="fd-splits">
            @foreach ($milestones as $milestone)
                <li class="fd-split">
                    <p class="fd-split-meta"><span>Split {{ Str::padLeft((string) $loop->iteration, 2, '0') }}</span>{{ $milestone['date'] }}</p>
                    <h3>{{ $milestone['title'] }}</h3>
                    <p>{{ $milestone['text'] }}</p>
                </li>
            @endforeach
        </ol>
    </div>
</section>

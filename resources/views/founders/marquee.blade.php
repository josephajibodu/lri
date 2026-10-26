<div class="marquee" aria-hidden="true">
    <div class="marquee-track">
        @foreach (range(1, 2) as $group)
            <div class="marquee-group">
                @foreach (range(1, 2) as $repeat)
                    <span>The founders</span><i>✱</i>
                    @foreach ($people as $person)
                        <span>{{ $person['name'] }}</span><i>✱</i>
                    @endforeach
                    <span>Est. {{ $founded }}</span><i>✱</i>
                @endforeach
            </div>
        @endforeach
    </div>
</div>

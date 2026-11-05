@if (config('lri.selar_embed.product'))
    <section class="sec sec-paper" id="tickets">
        <div class="wrap">
            <div class="sec-head">
                <p class="label">Tickets</p>
                <h2>Get your ticket</h2>
            </div>
            {{-- One widget per button behaviour (config lri.selar_embed.variants); keep only the one you want before going live. --}}
            @foreach (config('lri.selar_embed.variants') as $variant)
                <div class="selar-embed-variant" style="margin-top: 2rem">
                    @if (count(config('lri.selar_embed.variants')) > 1)
                        <p class="label">{{ $variant['label'] }}</p>
                    @endif
                    <script
                        src="{{ config('lri.selar_embed.script') }}"
                        data-product="{{ $variant['product'] ?? config('lri.selar_embed.product') }}"
                        @if ($variant['layout'] ?? null) data-layout="{{ $variant['layout'] }}" @endif
                        @if ($variant['action'] ?? null) data-action="{{ $variant['action'] }}" @endif
                        async
                    ></script>
                </div>
            @endforeach
        </div>
    </section>
@endif

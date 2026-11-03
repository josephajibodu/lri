{{-- The ways to pay, as submit buttons: featured ones up front, the rest folded under "More ways to pay". --}}
@php
    [$featured, $others] = $channels->partition(fn ($channel) => $channel->is_featured);

    if ($featured->isEmpty()) {
        $featured = $others->take(1);
        $others = $others->slice(1);
    }
@endphp

<div class="dr-pay-list">
    @foreach ($featured as $channel)
        <button class="dr-pay dr-pay-featured" type="submit" name="channel" value="{{ $channel->id }}" data-method="{{ $channel->method->value }}">
            <span class="sr-only">Pay with</span>
            <span class="dr-pay-name">{{ $channel->display_label }}</span>
            @if ($channel->description)
                <span class="dr-pay-note">{{ $channel->description }}</span>
            @endif
        </button>
    @endforeach

    @if ($others->isNotEmpty())
        <details class="dr-pay-more">
            <summary>More ways to pay</summary>
            <div class="dr-pay-list">
                @foreach ($others as $channel)
                    <button class="dr-pay" type="submit" name="channel" value="{{ $channel->id }}" data-method="{{ $channel->method->value }}">
                        <span class="dr-pay-name">{{ $channel->display_label }}</span>
                        @if ($channel->description)
                            <span class="dr-pay-note">{{ $channel->description }}</span>
                        @endif
                    </button>
                @endforeach
            </div>
        </details>
    @endif
</div>

<p class="dr-checkout-status" data-checkout-status role="status" hidden></p>

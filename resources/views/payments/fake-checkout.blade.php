<x-layouts.app title="Fake checkout · Let’s Run Ibadan" robots="noindex, nofollow">
    <main class="dr">
        <section class="dr-sec">
            <div class="wrap">
                <div class="dr-order">
                    <p class="dr-label">Fake payment provider · local only</p>
                    <h1 class="dr-order-title">Pay <em>{{ $amount->format() }}</em></h1>
                    <p class="dr-sub">This page stands in for the real payment page while PAYMENTS_FAKE is on. Nothing is charged. Reference {{ $checkout['reference'] }}, by {{ Str::lower($method->label()) }}.</p>

                    <form class="dr-pay-list" method="POST" action="{{ route('payments.fake.settle', $checkout['checkout_id']) }}">
                        @csrf
                        <button class="dr-pay dr-pay-featured" type="submit" name="outcome" value="paid">
                            <span class="dr-pay-name">Pay</span>
                            <span class="dr-pay-note">The payment succeeds.</span>
                        </button>
                        @foreach ($failureReasons as $reason)
                            <button class="dr-pay" type="submit" name="reason" value="{{ $reason->value }}" formaction="{{ route('payments.fake.settle', $checkout['checkout_id']) }}?outcome=failed">
                                <span class="dr-pay-name">Fail: {{ Str::lower($reason->label()) }}</span>
                            </button>
                        @endforeach
                        <button class="dr-pay" type="submit" name="outcome" value="left">
                            <span class="dr-pay-name">Leave without paying</span>
                        </button>
                    </form>
                </div>
            </div>
        </section>
    </main>
</x-layouts.app>

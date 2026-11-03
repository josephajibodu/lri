<?php

use App\Enums\PaymentFailureReason;
use App\Enums\PaymentMethod;
use App\Enums\PaymentStatus;
use App\Payments\Data\CheckoutRequest;
use App\Payments\Exceptions\PaymentProviderException;
use App\Payments\PaymentManager;
use App\Payments\Providers\BachsProvider;
use App\Support\Money;
use Illuminate\Http\Client\Request as ClientRequest;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Sleep;

beforeEach(function () {
    Http::preventStrayRequests();
    Sleep::fake();

    config()->set('payments.providers.bachs.secret_key', 'sk_sandbox_test');
    config()->set('payments.providers.bachs.webhook_secret', 'whsec_test');
    config()->set('payments.providers.bachs.base_url', null);
});

function bachs(): BachsProvider
{
    return app(PaymentManager::class)->driver('bachs');
}

function checkoutRequest(array $overrides = []): CheckoutRequest
{
    return new CheckoutRequest(...[
        'reference' => 'LRI-7K3Q9P-4MXA',
        'amount' => Money::of(2_000_000, 'NGN'),
        'customerEmail' => 'ada@example.com',
        'customerName' => 'Ada Bello',
        'customerPhone' => '+2348012345678',
        'successUrl' => 'https://lri.test/orders/LRI-7K3Q9P/paid',
        'cancelUrl' => 'https://lri.test/disco-run',
        'methods' => [PaymentMethod::BankTransfer],
        'expiresInMinutes' => 60,
        'metadata' => ['order' => 'LRI-7K3Q9P'],
        ...$overrides,
    ]);
}

/**
 * A webhook request signed the way Bachs signs them.
 */
function signedBachsWebhook(string $body, ?int $timestamp = null, string $secret = 'whsec_test', bool $legacyHeaders = false): Request
{
    $timestamp ??= now()->getTimestamp();
    $signature = hash_hmac('sha256', "{$timestamp}.{$body}", $secret);

    $headers = $legacyHeaders
        ? ['HTTP_X_BACHS_TIMESTAMP' => (string) $timestamp, 'HTTP_X_BACHS_SIGNATURE' => $signature]
        : ['HTTP_X_BACHS_SIGNATURE_V2' => "t={$timestamp},v1={$signature}"];

    return Request::create('/webhooks/bachs', 'POST', server: $headers, content: $body);
}

test('opening a checkout asks Bachs for a naira checkout limited to the chosen method', function () {
    Http::fake(['sandbox-api.bachs.io/v1/checkout-sessions' => Http::response([
        'checkout_id' => 'chk_123',
        'checkout_url' => 'https://checkout.bachs.io/c/chk_123',
        'status' => 'open',
        'expires_at' => '2026-11-03T21:00:00Z',
        'created_at' => '2026-11-03T20:00:00Z',
    ], 201)]);

    $session = bachs()->createCheckout(checkoutRequest());

    expect($session->checkoutId)->toBe('chk_123')
        ->and($session->checkoutUrl)->toBe('https://checkout.bachs.io/c/chk_123')
        ->and($session->expiresAt->toIso8601ZuluString())->toBe('2026-11-03T21:00:00Z');

    Http::assertSent(fn (ClientRequest $request): bool => $request->method() === 'POST'
        && $request->hasHeader('Authorization', 'Bearer sk_sandbox_test')
        && $request->hasHeader('Idempotency-Key', 'LRI-7K3Q9P-4MXA')
        && $request->data() === [
            'pricing' => ['currency' => 'NGN', 'amount' => '20000.00'],
            'customer' => ['email' => 'ada@example.com', 'name' => 'Ada Bello', 'phone_number' => '+2348012345678'],
            'success_url' => 'https://lri.test/orders/LRI-7K3Q9P/paid',
            'cancel_url' => 'https://lri.test/disco-run',
            'payment_method_types' => ['NGN_BANK_TRANSFER'],
            'reference' => 'LRI-7K3Q9P-4MXA',
            'metadata' => ['order' => 'LRI-7K3Q9P'],
            'expires_in_minutes' => 60,
        ]);
});

test('a checkout with no chosen method offers every method and leaves out a phone Bachs would reject', function () {
    Http::fake(['*' => Http::response(['checkout_id' => 'chk_1', 'checkout_url' => 'https://checkout.bachs.io/c/chk_1', 'expires_at' => null], 201)]);

    bachs()->createCheckout(checkoutRequest(['methods' => [], 'customerPhone' => '08012345678']));

    Http::assertSent(fn (ClientRequest $request): bool => ! array_key_exists('payment_method_types', $request->data())
        && $request->data()['customer'] === ['email' => 'ada@example.com', 'name' => 'Ada Bello']);
});

test('a live key talks to the live API', function () {
    config()->set('payments.providers.bachs.secret_key', 'sk_live_test');
    Http::fake(['api.bachs.io/*' => Http::response(['checkout_id' => 'chk_1', 'checkout_url' => 'https://checkout.bachs.io/c/chk_1'], 201)]);

    bachs()->createCheckout(checkoutRequest());

    Http::assertSent(fn (ClientRequest $request): bool => str_starts_with($request->url(), 'https://api.bachs.io/v1/'));
});

test('a method Bachs cannot take in the currency is refused before calling Bachs', function () {
    bachs()->createCheckout(checkoutRequest(['methods' => [PaymentMethod::Opay]]));
})->throws(InvalidArgumentException::class, 'Bachs cannot take OPay in NGN.');

test('an error from Bachs carries its error code', function () {
    Http::fake(['*' => Http::response(['detail' => 'Amount is below the minimum', 'error_code' => 'VALIDATION_ERROR'], 400)]);

    try {
        bachs()->createCheckout(checkoutRequest());
        $this->fail('Expected a PaymentProviderException.');
    } catch (PaymentProviderException $exception) {
        expect($exception->errorCode)->toBe('VALIDATION_ERROR')
            ->and($exception->httpStatus)->toBe(400)
            ->and($exception->getMessage())->toBe('bachs returned 400: Amount is below the minimum');
    }

    Http::assertSentCount(1);
});

test('a brief Bachs outage is retried with the same idempotency key', function () {
    Http::fake(['*' => Http::sequence()
        ->push(['detail' => 'Down', 'error_code' => 'SERVICE_UNAVAILABLE'], 503)
        ->push(['checkout_id' => 'chk_1', 'checkout_url' => 'https://checkout.bachs.io/c/chk_1'], 201)]);

    expect(bachs()->createCheckout(checkoutRequest())->checkoutId)->toBe('chk_1');

    Http::assertSentCount(2);
    Http::assertSent(fn (ClientRequest $request): bool => $request->hasHeader('Idempotency-Key', 'LRI-7K3Q9P-4MXA'));
});

test('not reaching Bachs is reported as a provider error', function () {
    Http::fake(['*' => Http::failedConnection()]);

    bachs()->createCheckout(checkoutRequest());
})->throws(PaymentProviderException::class, 'Could not reach Bachs');

test('Bachs without a secret key is reported as not set up', function () {
    config()->set('payments.providers.bachs.secret_key', null);

    bachs()->createCheckout(checkoutRequest());
})->throws(PaymentProviderException::class, 'BACHS_SECRET_KEY is empty');

test('the methods switched on for our account come from the checkout settings', function () {
    Http::fake(['sandbox-api.bachs.io/v1/accounts/checkout/settings' => Http::response([
        'enabled_payment_methods' => [
            'NGN_CARD' => ['enabled' => false],
            'NGN_BANK_TRANSFER' => ['enabled' => true],
            'USD_CARD' => ['enabled' => true],
        ],
    ])]);

    expect(bachs()->enabledMethods('NGN'))->toBe([PaymentMethod::BankTransfer])
        ->and(bachs()->supportedMethods('NGN'))->toBe([PaymentMethod::Card, PaymentMethod::BankTransfer]);
});

test('asking about a checkout reads its charge', function (array $session, PaymentStatus $status, ?PaymentFailureReason $reason) {
    Http::fake(['sandbox-api.bachs.io/v1/checkout-sessions/chk_123' => Http::response([
        'checkout_id' => 'chk_123',
        'amount' => '20000.00',
        'currency' => 'NGN',
        'reference' => 'LRI-7K3Q9P-4MXA',
        'customer' => ['id' => null, 'email' => 'ada@example.com', 'name' => 'Ada Bello'],
        'created_at' => '2026-11-03T20:00:00Z',
        'updated_at' => '2026-11-03T20:05:00Z',
        ...$session,
    ])]);

    $update = bachs()->fetchCheckout('chk_123');

    expect($update->status)->toBe($status)
        ->and($update->failureReason)->toBe($reason)
        ->and($update->reference)->toBe('LRI-7K3Q9P-4MXA')
        ->and($update->amount->equals(Money::of(2_000_000, 'NGN')))->toBeTrue();
})->with([
    'not paid yet' => [['status' => 'open', 'charge' => null], PaymentStatus::Open, null],
    'paid' => [['status' => 'completed', 'charge' => ['payment_id' => 'pay_1', 'status' => 'succeeded']], PaymentStatus::Succeeded, null],
    'a failed try that can be retried' => [['status' => 'open', 'charge' => ['payment_id' => 'pay_1', 'status' => 'failed', 'message' => 'Insufficient funds']], PaymentStatus::Open, PaymentFailureReason::InsufficientFunds],
    'expired' => [['status' => 'expired', 'charge' => null], PaymentStatus::Expired, null],
    'cancelled' => [['status' => 'cancelled', 'charge' => null], PaymentStatus::Abandoned, null],
    'underpaid' => [['status' => 'completed', 'charge' => ['payment_id' => 'pay_1', 'status' => 'underpaid']], PaymentStatus::Underpaid, null],
]);

test('a paid checkout reports the method and the fee', function () {
    Http::fake(['*' => Http::response([
        'checkout_id' => 'chk_123',
        'status' => 'completed',
        'amount' => '20000.00',
        'currency' => 'NGN',
        'payment_method' => 'NGN_BANK_TRANSFER',
        'charge' => ['payment_id' => 'pay_1', 'status' => 'succeeded', 'fees' => ['amount' => '300.00', 'currency' => 'NGN']],
        'customer' => ['id' => null, 'email' => null, 'name' => null],
        'created_at' => '2026-11-03T20:00:00Z',
        'updated_at' => '2026-11-03T20:05:00Z',
    ])]);

    $update = bachs()->fetchCheckout('chk_123');

    expect($update->method)->toBe(PaymentMethod::BankTransfer)
        ->and($update->paymentId)->toBe('pay_1')
        ->and($update->fee->equals(Money::of(30_000, 'NGN')))->toBeTrue()
        ->and($update->providerStatus)->toBe('completed/succeeded');
});

test('a webhook signed with our secret is trusted', function (bool $legacyHeaders) {
    expect(bachs()->verifyWebhook(signedBachsWebhook('{"id":"evt_1"}', legacyHeaders: $legacyHeaders)))->toBeTrue();
})->with(['the V2 header' => false, 'the older headers' => true]);

test('during a secret rotation either signature is enough', function () {
    $timestamp = now()->getTimestamp();
    $body = '{"id":"evt_1"}';
    $old = hash_hmac('sha256', "{$timestamp}.{$body}", 'whsec_old');
    $new = hash_hmac('sha256', "{$timestamp}.{$body}", 'whsec_test');

    $request = Request::create('/webhooks/bachs', 'POST', server: ['HTTP_X_BACHS_SIGNATURE_V2' => "t={$timestamp},v1={$old},v1={$new}"], content: $body);

    expect(bachs()->verifyWebhook($request))->toBeTrue();
});

test('a webhook is not trusted when its signature, age or our secret is wrong', function (Request $request) {
    expect(bachs()->verifyWebhook($request))->toBeFalse();
})->with([
    'signed with another secret' => fn () => signedBachsWebhook('{"id":"evt_1"}', secret: 'whsec_other'),
    'body changed after signing' => function () {
        $request = signedBachsWebhook('{"id":"evt_1"}');

        return Request::create('/webhooks/bachs', 'POST', server: ['HTTP_X_BACHS_SIGNATURE_V2' => $request->header('X-Bachs-Signature-V2')], content: '{"id":"evt_2"}');
    },
    'older than five minutes' => fn () => signedBachsWebhook('{"id":"evt_1"}', timestamp: now()->subMinutes(6)->getTimestamp()),
    'not signed' => fn () => Request::create('/webhooks/bachs', 'POST', content: '{"id":"evt_1"}'),
    'no secret set up' => function () {
        config()->set('payments.providers.bachs.webhook_secret', null);

        return signedBachsWebhook('{"id":"evt_1"}');
    },
]);

test('a succeeded collection reads as paid with its method and fee', function () {
    $event = bachs()->parseWebhook([
        'id' => 'evt_1',
        'type' => 'collection.succeeded',
        'created_at' => '2026-09-22T10:41:18.402Z',
        'data' => [
            'charge_id' => 'ch_1',
            'checkout_id' => 'chk_123',
            'reference' => 'LRI-7K3Q9P-4MXA',
            'status' => 'SUCCEEDED',
            'amount' => '20000.00',
            'currency' => 'NGN',
            'processing_fee' => '300.00',
            'processing_fee_currency' => 'NGN',
            'payment_method_details' => ['type' => 'bank_transfer'],
        ],
    ]);

    expect($event->id)->toBe('evt_1')
        ->and($event->createdAt->toIso8601ZuluString('millisecond'))->toBe('2026-09-22T10:41:18.402Z')
        ->and($event->payment->status)->toBe(PaymentStatus::Succeeded)
        ->and($event->payment->checkoutId)->toBe('chk_123')
        ->and($event->payment->paymentId)->toBe('ch_1')
        ->and($event->payment->method)->toBe(PaymentMethod::BankTransfer)
        ->and($event->payment->amount->equals(Money::of(2_000_000, 'NGN')))->toBeTrue()
        ->and($event->payment->fee->equals(Money::of(30_000, 'NGN')))->toBeTrue();
});

test('a failed collection keeps Bachs’s wording and reads it as one of our reasons', function (string $reason, PaymentFailureReason $expected) {
    $event = bachs()->parseWebhook([
        'id' => 'evt_1',
        'type' => 'collection.failed',
        'data' => ['checkout_id' => 'chk_123', 'status' => 'FAILED', 'amount' => '20000.00', 'currency' => 'NGN', 'reason' => $reason],
    ]);

    expect($event->payment->status)->toBe(PaymentStatus::Failed)
        ->and($event->payment->failureReason)->toBe($expected)
        ->and($event->payment->failureMessage)->toBe($reason);
})->with([
    ['Insufficient funds in account', PaymentFailureReason::InsufficientFunds],
    ['Payment authorization failed', PaymentFailureReason::Declined],
    ['Card declined by issuer', PaymentFailureReason::Declined],
    ['Incorrect OTP entered', PaymentFailureReason::AuthenticationFailed],
    ['Expired card', PaymentFailureReason::ExpiredCard],
    ['Transaction timed out', PaymentFailureReason::TimedOut],
    ['Something odd happened', PaymentFailureReason::Unknown],
]);

test('a collection that closed unpaid reads as expired', function () {
    $event = bachs()->parseWebhook(['id' => 'evt_1', 'type' => 'collection.failed', 'data' => ['checkout_id' => 'chk_123', 'status' => 'EXPIRED']]);

    expect($event->payment->status)->toBe(PaymentStatus::Expired)
        ->and($event->payment->failureReason)->toBeNull();
});

test('checkout events read as paid or expired', function () {
    $completed = bachs()->parseWebhook(['id' => 'evt_1', 'type' => 'checkout.completed', 'data' => [
        'checkout_id' => 'chk_123', 'payment_status' => 'paid', 'amount' => '20000.00', 'currency' => 'NGN', 'charge' => ['id' => 'ch_1'],
    ]]);
    $expired = bachs()->parseWebhook(['id' => 'evt_2', 'type' => 'checkout.expired', 'data' => ['checkout_id' => 'chk_456', 'reference' => 'LRI-AAAAAA-BBBB']]);

    expect($completed->payment->status)->toBe(PaymentStatus::Succeeded)
        ->and($completed->payment->paymentId)->toBe('ch_1')
        ->and($expired->payment->status)->toBe(PaymentStatus::Expired)
        ->and($expired->payment->reference)->toBe('LRI-AAAAAA-BBBB');
});

test('events the site does not act on carry no payment update', function () {
    expect(bachs()->parseWebhook(['id' => 'evt_1', 'type' => 'customer.created', 'data' => []])->payment)->toBeNull()
        ->and(bachs()->parseWebhook(['id' => 'evt_2', 'type' => 'checkout.completed', 'data' => ['payment_status' => 'no_payment_required']])->payment)->toBeNull();
});

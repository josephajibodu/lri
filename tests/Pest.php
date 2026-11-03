<?php

use App\Models\Payment;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\Request as ClientRequest;
use Illuminate\Support\Facades\Http;
use Illuminate\Testing\TestResponse;
use Tests\TestCase;

/*
|--------------------------------------------------------------------------
| Test Case
|--------------------------------------------------------------------------
|
| The closure you provide to your test functions is always bound to a specific PHPUnit test
| case class. By default, that class is "PHPUnit\Framework\TestCase". Of course, you may
| need to change it using the "pest()" function to bind different classes or traits.
|
*/

pest()->extend(TestCase::class)
 // ->use(RefreshDatabase::class)
    ->in('Feature');

/*
|--------------------------------------------------------------------------
| Expectations
|--------------------------------------------------------------------------
|
| When you're writing tests, you often need to check that values meet certain conditions. The
| "expect()" function gives you access to a set of "expectations" methods that you can use
| to assert different things. Of course, you may extend the Expectation API at any time.
|
*/

expect()->extend('toBeOne', function () {
    return $this->toBe(1);
});

/*
|--------------------------------------------------------------------------
| Functions
|--------------------------------------------------------------------------
|
| While Pest is very powerful out-of-the-box, you may have some testing code specific to your
| project that you don't want to repeat in every file. Here you can also expose helpers as
| global functions to help you to reduce the number of lines of code in your test files.
|
*/

function something()
{
    // ..
}

/**
 * Point payments at Bachs's sandbox with its API faked, so no request leaves the test. Each checkout opened
 * gets an id made from the attempt's reference, so every attempt has its own.
 */
function fakeBachs(): void
{
    config()->set('payments.providers.bachs.secret_key', 'sk_sandbox_test');
    config()->set('payments.providers.bachs.webhook_secret', 'whsec_test');
    config()->set('payments.providers.bachs.base_url', null);

    Http::preventStrayRequests();
    Http::fake(['sandbox-api.bachs.io/v1/checkout-sessions' => fn (ClientRequest $request) => Http::response([
        'checkout_id' => 'chk_'.$request['reference'],
        'checkout_url' => 'https://checkout.bachs.io/c/chk_'.$request['reference'],
        'status' => 'open',
        'expires_at' => now()->addMinutes($request['expires_in_minutes'])->toIso8601String(),
        'created_at' => now()->toIso8601String(),
    ], 201)]);
}

/**
 * What Bachs answers when asked about a payment's checkout: paid in full by bank transfer, or not paid yet.
 *
 * @return array<string, mixed>
 */
function bachsCheckoutSession(Payment $payment, bool $paid = true): array
{
    return [
        'checkout_id' => $payment->provider_checkout_id,
        'status' => $paid ? 'completed' : 'open',
        'reference' => $payment->reference,
        'amount' => $payment->money()->toDecimal(),
        'currency' => $payment->currency,
        'payment_method' => 'NGN_BANK_TRANSFER',
        'updated_at' => now()->toIso8601String(),
        'charge' => $paid ? ['payment_id' => 'pay_'.$payment->reference, 'status' => 'succeeded'] : null,
    ];
}

/**
 * Have Bachs say a payment's checkout is paid whenever it is asked.
 */
function fakePaidBachsCheckout(Payment $payment): void
{
    Http::fake(['sandbox-api.bachs.io/v1/checkout-sessions/'.$payment->provider_checkout_id => Http::response(bachsCheckoutSession($payment))]);
}

/**
 * A Bachs webhook event, about checkout chk_123 for ₦20,000 unless the data says otherwise.
 *
 * @param  array<string, mixed>  $data
 * @return array<string, mixed>
 */
function bachsEvent(string $type, array $data = [], string $id = 'evt_1'): array
{
    return [
        'id' => $id,
        'type' => $type,
        'created_at' => now()->toIso8601String(),
        'organization_id' => 'acct_1',
        'data' => ['checkout_id' => 'chk_123', 'amount' => '20000.00', 'currency' => 'NGN', ...$data],
    ];
}

/**
 * Send a webhook event to the site, signed the way Bachs signs them.
 *
 * @param  array<string, mixed>  $event
 */
function deliverBachsWebhook(array $event, string $secret = 'whsec_test'): TestResponse
{
    $body = json_encode($event);
    $timestamp = now()->getTimestamp();
    $signature = hash_hmac('sha256', "{$timestamp}.{$body}", $secret);

    return test()->call('POST', route('webhooks.payments', 'bachs'), server: [
        'CONTENT_TYPE' => 'application/json',
        'HTTP_X_BACHS_SIGNATURE_V2' => "t={$timestamp},v1={$signature}",
    ], content: $body);
}

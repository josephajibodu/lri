<?php

use App\Enums\PaymentFailureReason;
use App\Enums\PaymentMethod;
use App\Enums\PaymentStatus;
use App\Payments\Data\CheckoutRequest;
use App\Payments\Exceptions\PaymentProviderException;
use App\Payments\PaymentManager;
use App\Payments\Providers\FakeProvider;
use App\Support\Money;
use Illuminate\Http\Request;

function fakePayments(): FakeProvider
{
    return app(PaymentManager::class)->driver('fake');
}

function openFakeCheckout(): string
{
    return fakePayments()->createCheckout(new CheckoutRequest(
        reference: 'LRI-7K3Q9P-4MXA',
        amount: Money::of(1_000_000, 'NGN'),
        customerEmail: 'ada@example.com',
        customerName: 'Ada Bello',
        customerPhone: null,
        successUrl: 'https://lri.test/paid',
        cancelUrl: 'https://lri.test/disco-run',
        methods: [PaymentMethod::BankTransfer],
    ))->checkoutId;
}

test('a fake checkout starts open and is remembered', function () {
    $checkoutId = openFakeCheckout();

    expect(fakePayments()->fetchCheckout($checkoutId)->status)->toBe(PaymentStatus::Open)
        ->and(fakePayments()->checkout($checkoutId)['methods'])->toBe(['bank_transfer'])
        ->and(fakePayments()->requests())->toHaveCount(1);
});

test('settling a fake checkout gives a webhook the fake provider trusts and reads', function () {
    $checkoutId = openFakeCheckout();
    $payload = fakePayments()->simulate($checkoutId, PaymentStatus::Failed, PaymentMethod::Card, PaymentFailureReason::InsufficientFunds);
    $body = json_encode($payload);

    $request = Request::create('/webhooks/fake', 'POST', server: ['HTTP_X_FAKE_SIGNATURE' => fakePayments()->sign($body)], content: $body);
    $forged = Request::create('/webhooks/fake', 'POST', server: ['HTTP_X_FAKE_SIGNATURE' => 'nope'], content: $body);
    $event = fakePayments()->parseWebhook($payload);

    expect(fakePayments()->verifyWebhook($request))->toBeTrue()
        ->and(fakePayments()->verifyWebhook($forged))->toBeFalse()
        ->and($event->payment->status)->toBe(PaymentStatus::Failed)
        ->and($event->payment->method)->toBe(PaymentMethod::Card)
        ->and($event->payment->failureReason)->toBe(PaymentFailureReason::InsufficientFunds)
        ->and($event->payment->reference)->toBe('LRI-7K3Q9P-4MXA')
        ->and(fakePayments()->fetchCheckout($checkoutId)->status)->toBe(PaymentStatus::Failed);
});

test('asking about an unknown fake checkout is a provider error', function () {
    fakePayments()->fetchCheckout('fake_chk_missing');
})->throws(PaymentProviderException::class);

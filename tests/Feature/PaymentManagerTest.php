<?php

use App\Models\PaymentChannel;
use App\Payments\PaymentManager;
use App\Payments\Providers\BachsProvider;
use App\Payments\Providers\FakeProvider;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

test('a channel is handled by the provider it names', function () {
    config()->set('payments.fake', false);

    $channel = PaymentChannel::factory()->create(['provider' => 'bachs']);

    expect(app(PaymentManager::class)->forChannel($channel))->toBeInstanceOf(BachsProvider::class);
});

test('fake payments send every channel through the fake provider', function () {
    config()->set('payments.fake', true);

    $channel = PaymentChannel::factory()->create(['provider' => 'bachs']);

    expect(app(PaymentManager::class)->forChannel($channel))->toBeInstanceOf(FakeProvider::class);
});

test('fake payments are never used in production', function () {
    config()->set('payments.fake', true);
    app()->detectEnvironment(fn (): string => 'production');

    $payments = app(PaymentManager::class);

    expect($payments->usesFakePayments())->toBeFalse()
        ->and($payments->provider('bachs'))->toBeInstanceOf(BachsProvider::class)
        ->and(fn () => $payments->driver('fake'))->toThrow(LogicException::class);
});

<?php

use App\Models\PaymentChannel;
use App\Payments\PaymentManager;
use App\Payments\Providers\BachsProvider;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

test('a channel is handled by the provider it names', function () {
    $channel = PaymentChannel::factory()->create(['provider' => 'bachs']);

    expect(app(PaymentManager::class)->forChannel($channel))->toBeInstanceOf(BachsProvider::class);
});

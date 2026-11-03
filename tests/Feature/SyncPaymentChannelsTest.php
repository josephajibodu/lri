<?php

use App\Enums\PaymentMethod;
use App\Models\PaymentChannel;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;

uses(RefreshDatabase::class);

beforeEach(function () {
    Http::preventStrayRequests();

    config()->set('payments.default', 'bachs');
    config()->set('payments.providers.bachs.secret_key', 'sk_sandbox_test');
});

function fakeBachsSettings(array $enabled): void
{
    Http::fake(['sandbox-api.bachs.io/v1/accounts/checkout/settings' => Http::response([
        'enabled_payment_methods' => collect($enabled)->map(fn (bool $on): array => ['enabled' => $on])->all(),
    ])]);
}

test('a method newly switched on at Bachs gets a channel', function () {
    fakeBachsSettings(['NGN_CARD' => true, 'NGN_BANK_TRANSFER' => true]);
    PaymentChannel::factory()->method(PaymentMethod::BankTransfer)->create(['sort_order' => 4]);

    $this->artisan('payments:sync-channels')->assertSuccessful();

    $card = PaymentChannel::query()->where('method', PaymentMethod::Card)->sole();

    expect($card->provider)->toBe('bachs')
        ->and($card->is_enabled)->toBeTrue()
        ->and($card->is_featured)->toBeFalse()
        ->and($card->sort_order)->toBe(5);
});

test('a method Bachs no longer allows is switched off and unfeatured', function () {
    fakeBachsSettings(['NGN_CARD' => true, 'NGN_BANK_TRANSFER' => false]);
    $transfer = PaymentChannel::factory()->method(PaymentMethod::BankTransfer)->featured()->create();

    $this->artisan('payments:sync-channels')->assertSuccessful();

    expect($transfer->fresh())->is_enabled->toBeFalse()->is_featured->toBeFalse();
});

test('admins’ choices and other providers’ channels are left alone', function () {
    fakeBachsSettings(['NGN_CARD' => true, 'NGN_BANK_TRANSFER' => true]);
    $card = PaymentChannel::factory()->method(PaymentMethod::Card)->disabled()->create();
    $transfer = PaymentChannel::factory()->method(PaymentMethod::BankTransfer)->create(['provider' => 'paystack', 'is_enabled' => false]);

    $this->artisan('payments:sync-channels')->assertSuccessful();

    expect($card->fresh()->is_enabled)->toBeFalse()
        ->and($transfer->fresh())->provider->toBe('paystack')->is_enabled->toBeFalse();
});

test('a Bachs error stops the sync without changing anything', function () {
    Http::fake(['*' => Http::response(['detail' => 'Invalid API key', 'error_code' => 'UNAUTHORIZED'], 401)]);
    $transfer = PaymentChannel::factory()->method(PaymentMethod::BankTransfer)->create();

    $this->artisan('payments:sync-channels')
        ->expectsOutputToContain('Invalid API key')
        ->assertFailed();

    expect($transfer->fresh()->is_enabled)->toBeTrue();
});

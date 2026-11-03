<?php

use App\Actions\Checkout\RecordPaymentUpdate;
use App\Enums\OrderStatus;
use App\Enums\PaymentFailureReason;
use App\Enums\PaymentMethod;
use App\Enums\PaymentStatus;
use App\Events\OrderPaid;
use App\Models\Order;
use App\Models\Payment;
use App\Payments\Data\PaymentUpdate;
use App\Support\Money;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Log;

uses(RefreshDatabase::class);

beforeEach(function () {
    Event::fake([OrderPaid::class]);

    $this->order = Order::factory()->create(['quantity' => 2, 'unit_price' => 1_000_000, 'amount' => 2_000_000]);
    $this->payment = Payment::factory()->for($this->order)->create(['amount' => 2_000_000]);
});

function record(Payment $payment, PaymentStatus $status, array $details = []): Payment
{
    return app(RecordPaymentUpdate::class)->handle($payment, new PaymentUpdate(...['status' => $status, 'providerStatus' => $status->value, ...$details]));
}

test('a succeeded payment marks the order paid and issues one ticket per seat, once', function () {
    $paid = record($this->payment, PaymentStatus::Succeeded, ['paymentId' => 'pay_1', 'method' => PaymentMethod::Card, 'amount' => Money::of(2_000_000, 'NGN'), 'fee' => Money::of(30_000, 'NGN')]);
    record($this->payment, PaymentStatus::Succeeded, ['amount' => Money::of(2_000_000, 'NGN')]);

    expect($paid)
        ->status->toBe(PaymentStatus::Succeeded)
        ->provider_payment_id->toBe('pay_1')
        ->method->toBe(PaymentMethod::Card)
        ->fee->toBe(30_000)
        ->and($paid->paid_at)->not->toBeNull()
        ->and($this->order->fresh()->status)->toBe(OrderStatus::Paid)
        ->and($this->order->tickets()->count())->toBe(2);

    Event::assertDispatchedTimes(OrderPaid::class, 1);
});

test('a payment that brought in less than the total is recorded as underpaid and not fulfilled', function () {
    $payment = record($this->payment, PaymentStatus::Succeeded, ['amount' => Money::of(1_000_000, 'NGN')]);

    expect($payment)
        ->status->toBe(PaymentStatus::Underpaid)
        ->failure_reason->toBe(PaymentFailureReason::AmountMismatch)
        ->and($this->order->fresh()->status)->toBe(OrderStatus::Pending)
        ->and($this->order->tickets()->count())->toBe(0);

    Event::assertNotDispatched(OrderPaid::class);
});

test('a payment in another currency is not fulfilled', function () {
    expect(record($this->payment, PaymentStatus::Succeeded, ['amount' => Money::of(2_000_000, 'USD')])->status)->toBe(PaymentStatus::Underpaid);
});

test('an update that arrives late can’t undo a payment', function (PaymentStatus $late) {
    record($this->payment, PaymentStatus::Succeeded);

    expect(record($this->payment, $late)->status)->toBe(PaymentStatus::Succeeded)
        ->and($this->order->fresh()->status)->toBe(OrderStatus::Paid);
})->with([PaymentStatus::Open, PaymentStatus::Failed, PaymentStatus::Expired]);

test('money that lands after the checkout closed still pays an abandoned order', function () {
    $this->order->update(['status' => OrderStatus::Abandoned, 'abandoned_at' => now()]);
    record($this->payment, PaymentStatus::Expired);

    record($this->payment, PaymentStatus::Succeeded);

    expect($this->order->fresh())->status->toBe(OrderStatus::Paid)->abandoned_at->toBeNull();
});

test('a failed try on a checkout that is still open keeps the reason', function () {
    $payment = record($this->payment, PaymentStatus::Open, ['failureReason' => PaymentFailureReason::Declined, 'failureMessage' => 'Card declined']);

    expect($payment)
        ->status->toBe(PaymentStatus::Open)
        ->failure_reason->toBe(PaymentFailureReason::Declined)
        ->failure_message->toBe('Card declined');
});

test('a second payment on a paid order is flagged for a refund instead of issuing more tickets', function () {
    record($this->payment, PaymentStatus::Succeeded);
    $second = Payment::factory()->for($this->order)->create(['amount' => 2_000_000]);
    Log::spy();

    record($second, PaymentStatus::Succeeded);

    expect($this->order->tickets()->count())->toBe(2);
    Log::shouldHaveReceived('warning')->once()->withArgs(fn (string $message): bool => str_contains($message, 'paid for twice'));
});

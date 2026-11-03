<?php

use App\Enums\OrderStatus;
use App\Enums\PaymentMethod;
use App\Enums\PaymentStatus;
use App\Events\OrderAbandoned;
use App\Models\Order;
use App\Models\Payment;
use App\Models\PaymentChannel;
use App\Models\TicketType;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Mail;

uses(RefreshDatabase::class);

beforeEach(function () {
    Mail::fake();
    fakeBachs();

    $this->ticketType = TicketType::factory()->create();
    $this->channel = PaymentChannel::factory()->method(PaymentMethod::BankTransfer)->create();
});

/**
 * An order with an open Bachs checkout, placed the given number of minutes ago.
 */
function orderPlacedMinutesAgo(int $minutes): Order
{
    test()->travel(-$minutes)->minutes();
    test()->post(route('orders.store'), [
        'ticket_type' => test()->ticketType->slug,
        'quantity' => 1,
        'name' => 'Ada Bello',
        'email' => 'ada@example.com',
        'channel' => test()->channel->id,
    ]);
    test()->travelBack();

    return Order::query()->latest('id')->firstOrFail();
}

test('an open payment whose webhook never came is settled by asking the provider', function () {
    $order = orderPlacedMinutesAgo(10);
    fakePaidBachsCheckout($order->latestPayment);

    $this->artisan('payments:reconcile')->expectsOutputToContain('Checked 1 open payments; 1 have now settled.')->assertSuccessful();

    expect($order->fresh()->status)->toBe(OrderStatus::Paid);
});

test('payments just opened or checked recently are left alone', function () {
    $fresh = orderPlacedMinutesAgo(1);
    $checked = orderPlacedMinutesAgo(10);
    $checked->latestPayment->update(['last_checked_at' => now()->subMinute()]);

    $this->artisan('payments:reconcile')->expectsOutputToContain('Checked 0 open payments')->assertSuccessful();

    expect($fresh->fresh()->status)->toBe(OrderStatus::Pending);
});

test('an order past its hold with no payment is marked abandoned, and its tickets go back on sale', function () {
    Event::fake([OrderAbandoned::class]);
    $order = Order::factory()->for($this->ticketType)->create(['expires_at' => now()->subMinutes(15)]);
    Payment::factory()->for($order)->create(['status' => PaymentStatus::Expired, 'expires_at' => now()->subMinutes(15)]);

    $this->artisan('orders:abandon-unpaid')->expectsOutputToContain('Marked 1 unpaid orders abandoned.')->assertSuccessful();

    expect($order->fresh())->status->toBe(OrderStatus::Abandoned)->abandoned_at->not->toBeNull();
    Event::assertDispatched(OrderAbandoned::class, fn (OrderAbandoned $event): bool => $event->order->is($order));
});

test('an order still inside its grace period is not abandoned yet', function () {
    $order = Order::factory()->for($this->ticketType)->create(['expires_at' => now()->subMinutes(5)]);

    $this->artisan('orders:abandon-unpaid')->assertSuccessful();

    expect($order->fresh()->status)->toBe(OrderStatus::Pending);
});

test('an order whose payment turns out to be paid is fulfilled instead of abandoned', function () {
    Event::fake([OrderAbandoned::class]);
    $order = orderPlacedMinutesAgo(90);
    $order->update(['expires_at' => now()->subMinutes(30)]);
    $order->latestPayment->update(['expires_at' => now()->subMinutes(30)]);
    fakePaidBachsCheckout($order->latestPayment);

    $this->artisan('orders:abandon-unpaid')->assertSuccessful();

    expect($order->fresh()->status)->toBe(OrderStatus::Paid);
    Event::assertNotDispatched(OrderAbandoned::class);
});

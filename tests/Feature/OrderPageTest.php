<?php

use App\Enums\OrderStatus;
use App\Enums\PaymentFailureReason;
use App\Enums\PaymentMethod;
use App\Enums\PaymentStatus;
use App\Models\Order;
use App\Models\Payment;
use App\Models\PaymentChannel;
use App\Models\Ticket;
use App\Models\TicketType;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\Request as ClientRequest;
use Illuminate\Support\Facades\Http;

uses(RefreshDatabase::class);

beforeEach(function () {
    fakeBachs();

    $this->ticketType = TicketType::factory()->create(['price' => 1_000_000]);
    $this->transfer = PaymentChannel::factory()->method(PaymentMethod::BankTransfer)->featured()->create();
    $this->card = PaymentChannel::factory()->method(PaymentMethod::Card)->create();
});

/**
 * An order with an open Bachs checkout, the way the ticket form leaves it.
 */
function orderAwaitingPayment(int $quantity = 1): Order
{
    test()->post(route('orders.store'), [
        'ticket_type' => test()->ticketType->slug,
        'quantity' => $quantity,
        'name' => 'Ada Bello',
        'email' => 'ada@example.com',
        'channel' => test()->transfer->id,
    ]);

    return Order::query()->latest('id')->firstOrFail();
}

test('the order page only opens from its signed link', function () {
    $order = Order::factory()->for($this->ticketType)->create();

    $this->get(route('orders.show', $order->reference))->assertForbidden();
    $this->get($order->statusUrl())->assertOk()->assertSeeText($order->reference);
});

test('the order page still opens when the provider adds its checkout id to the link', function () {
    $order = Order::factory()->for($this->ticketType)->create();

    $this->get($order->statusUrl(['from' => 'checkout']).'&checkout_id=chk_123')->assertOk();
});

test('coming back from the checkout before the payment lands, the page asks the provider, then waits and keeps checking', function () {
    $order = orderAwaitingPayment();
    $payment = $order->latestPayment;
    Http::fake(['sandbox-api.bachs.io/v1/checkout-sessions/'.$payment->provider_checkout_id => Http::response(bachsCheckoutSession($payment, paid: false))]);

    $this->get($order->statusUrl(['from' => 'checkout']))
        ->assertOk()
        ->assertSeeText('Confirming your payment')
        ->assertSee('data-order-poll="'.e($order->signedUrl('orders.status')).'"', false);

    Http::assertSent(fn (ClientRequest $request): bool => $request->method() === 'GET' && str_ends_with($request->url(), $payment->provider_checkout_id));
});

test('coming back from a checkout the provider says is paid goes straight to the receipt', function () {
    $order = orderAwaitingPayment();
    fakePaidBachsCheckout($order->latestPayment);

    $this->get($order->statusUrl(['from' => 'checkout']).'&checkout_id='.$order->latestPayment->provider_checkout_id)
        ->assertRedirect($order->receiptUrl());

    expect($order->fresh()->status)->toBe(OrderStatus::Paid);
});

test('a paid order’s page sends the buyer on to its receipt', function () {
    $order = Order::factory()->for($this->ticketType)->paid()->create();

    $this->get($order->statusUrl())->assertRedirect($order->receiptUrl());
});

test('the receipt shows the tickets and what was paid, at an address with nothing about the payment in it', function () {
    $order = Order::factory()->for($this->ticketType)->paid()->create(['quantity' => 2, 'amount' => 2_000_000, 'tickets_emailed_at' => now()]);
    $tickets = Ticket::factory()->count(2)->for($order)->create(['ticket_type_id' => $order->ticket_type_id]);
    $payment = Payment::factory()->for($order)->method(PaymentMethod::BankTransfer)->succeeded()->create(['amount' => 2_000_000]);

    expect($order->receiptUrl())->not->toContain('?');

    $this->get($order->receiptUrl())
        ->assertOk()
        ->assertSeeText('You’re in')
        ->assertSeeText($tickets[0]->code)
        ->assertSeeText($tickets[1]->code)
        ->assertSeeText('₦20,000')
        ->assertSeeText('Bank transfer')
        ->assertSeeText($payment->reference)
        ->assertSeeText('We’ve emailed your tickets and this receipt to '.$order->buyer_email);
});

test('a receipt link for an order that isn’t paid opens its order page', function () {
    $order = Order::factory()->for($this->ticketType)->create();

    $this->get($order->receiptUrl())->assertRedirect($order->statusUrl());
});

test('a receipt can’t be opened with the order number or a made-up code', function () {
    $order = Order::factory()->for($this->ticketType)->paid()->create();

    $this->get('/receipts/'.$order->reference)->assertNotFound();
    $this->get('/receipts/not-a-real-receipt-code')->assertNotFound();
});

test('checking on a payment the provider says is paid issues the tickets', function () {
    $order = orderAwaitingPayment(quantity: 2);
    fakePaidBachsCheckout($order->latestPayment);

    $this->getJson($order->signedUrl('orders.status'))
        ->assertOk()
        ->assertJson(['state' => 'paid']);

    $order->refresh();

    expect($order->status)->toBe(OrderStatus::Paid)
        ->and($order->tickets)->toHaveCount(2)
        ->and($order->latestPayment->status)->toBe(PaymentStatus::Succeeded);
});

test('the provider is not asked again within a few seconds of the last check', function () {
    $order = orderAwaitingPayment();
    $payment = $order->latestPayment;
    Http::fake(['sandbox-api.bachs.io/v1/checkout-sessions/'.$payment->provider_checkout_id => Http::sequence()
        ->push(bachsCheckoutSession($payment, paid: false))
        ->push(bachsCheckoutSession($payment))]);
    $this->getJson($order->signedUrl('orders.status'))->assertJson(['state' => 'waiting']);

    $this->getJson($order->signedUrl('orders.status'))->assertJson(['state' => 'waiting']);

    $this->travel(6)->seconds();

    $this->getJson($order->signedUrl('orders.status'))->assertJson(['state' => 'paid']);
});

test('a failed payment explains what happened and offers every way to pay again', function () {
    $order = orderAwaitingPayment();
    deliverBachsWebhook(bachsEvent('collection.failed', [
        'checkout_id' => $order->latestPayment->provider_checkout_id,
        'amount' => '10000.00',
        'status' => 'FAILED',
        'reason' => 'Insufficient funds',
        'payment_method_details' => ['type' => 'card'],
    ]))->assertOk();
    $this->getJson($order->signedUrl('orders.status'))->assertJson(['state' => 'failed']);

    $this->get($order->statusUrl())
        ->assertSeeText('Your payment didn’t go through')
        ->assertSeeText(PaymentFailureReason::InsufficientFunds->advice())
        ->assertSee('action="'.e($order->signedUrl('orders.pay')).'"', false)
        ->assertSee('name="channel" value="'.$this->card->id.'"', false);
});

test('paying again opens a new attempt with another method', function () {
    $order = orderAwaitingPayment();

    $this->post($order->signedUrl('orders.pay'), ['channel' => $this->card->id])->assertRedirect();

    expect($order->payments()->count())->toBe(2)
        ->and($order->latestPayment()->first()->method)->toBe(PaymentMethod::Card);
});

test('an order that expired gets its tickets back when it is paid again, if any are left', function () {
    $this->ticketType->update(['capacity' => 1]);
    $order = Order::factory()->for($this->ticketType)->abandoned()->create(['quantity' => 1]);

    $this->post($order->signedUrl('orders.pay'), ['channel' => $this->transfer->id])->assertRedirect();

    expect($order->fresh())->status->toBe(OrderStatus::Pending)
        ->and($order->fresh()->expires_at->isFuture())->toBeTrue();
});

test('an expired order can’t take tickets someone else has since bought', function () {
    $this->ticketType->update(['capacity' => 1]);
    $order = Order::factory()->for($this->ticketType)->abandoned()->create(['quantity' => 1]);
    Order::factory()->for($this->ticketType)->paid()->create(['quantity' => 1]);

    $this->post($order->signedUrl('orders.pay'), ['channel' => $this->transfer->id])
        ->assertRedirect($order->statusUrl())
        ->assertSessionHas('checkout_error', 'Sorry, tickets just sold out.');

    expect($order->payments()->count())->toBe(0);
});

test('a paid order can’t be paid again', function () {
    $order = Order::factory()->for($this->ticketType)->paid()->create();

    $this->postJson($order->signedUrl('orders.pay'), ['channel' => $this->transfer->id])
        ->assertConflict()
        ->assertJsonPath('message', 'This order is already paid for.');
});

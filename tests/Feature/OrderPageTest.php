<?php

use App\Enums\OrderStatus;
use App\Enums\PaymentFailureReason;
use App\Enums\PaymentMethod;
use App\Enums\PaymentStatus;
use App\Models\Order;
use App\Models\Payment;
use App\Models\PaymentChannel;
use App\Models\TicketType;
use App\Models\WebhookCall;
use App\Payments\PaymentManager;
use App\Payments\Providers\FakeProvider;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

beforeEach(function () {
    config()->set('payments.fake', true);

    $this->ticketType = TicketType::factory()->create(['price' => 1_000_000]);
    $this->transfer = PaymentChannel::factory()->method(PaymentMethod::BankTransfer)->featured()->create();
    $this->card = PaymentChannel::factory()->method(PaymentMethod::Card)->create();
});

/**
 * An order with an open fake checkout, the way the ticket form leaves it.
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

function fakeCheckouts(): FakeProvider
{
    return app(PaymentManager::class)->driver('fake');
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

test('coming back from the checkout, the page waits for the payment and checks on it', function () {
    $order = orderAwaitingPayment();

    $this->get($order->statusUrl(['from' => 'checkout']))
        ->assertOk()
        ->assertSeeText('Confirming your payment')
        ->assertSee('data-order-poll="'.e($order->signedUrl('orders.status')).'"', false);
});

test('checking on a payment the provider says is paid issues the tickets', function () {
    $order = orderAwaitingPayment(quantity: 2);
    fakeCheckouts()->simulate($order->latestPayment->provider_checkout_id, PaymentStatus::Succeeded, PaymentMethod::BankTransfer);

    $this->getJson($order->signedUrl('orders.status'))
        ->assertOk()
        ->assertJson(['state' => 'paid']);

    $order->refresh();

    expect($order->status)->toBe(OrderStatus::Paid)
        ->and($order->tickets)->toHaveCount(2)
        ->and($order->latestPayment->status)->toBe(PaymentStatus::Succeeded);

    $this->get($order->statusUrl())
        ->assertSeeText('You’re in')
        ->assertSeeText($order->tickets[0]->code)
        ->assertSeeText($order->tickets[1]->code)
        ->assertDontSee('data-order-poll', false);
});

test('the provider is not asked again within a few seconds of the last check', function () {
    $order = orderAwaitingPayment();
    $this->getJson($order->signedUrl('orders.status'))->assertJson(['state' => 'waiting']);
    fakeCheckouts()->simulate($order->latestPayment->provider_checkout_id, PaymentStatus::Succeeded);

    $this->getJson($order->signedUrl('orders.status'))->assertJson(['state' => 'waiting']);

    $this->travel(6)->seconds();

    $this->getJson($order->signedUrl('orders.status'))->assertJson(['state' => 'paid']);
});

test('a failed payment explains what happened and offers every way to pay again', function () {
    $order = orderAwaitingPayment();
    fakeCheckouts()->simulate($order->latestPayment->provider_checkout_id, PaymentStatus::Failed, PaymentMethod::Card, PaymentFailureReason::InsufficientFunds);
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

test('the fake checkout pays and returns the buyer to their order', function () {
    $order = orderAwaitingPayment();
    $payment = $order->latestPayment;

    $this->get('/payments/fake/'.$payment->provider_checkout_id)->assertOk()->assertSeeText('₦10,000');

    $response = $this->post('/payments/fake/'.$payment->provider_checkout_id, ['outcome' => 'paid']);

    expect($response->headers->get('Location'))->toContain('/orders/'.$order->reference)->toContain('checkout_id=')
        ->and($order->fresh()->status)->toBe(OrderStatus::Paid)
        ->and(WebhookCall::query()->where('provider', 'fake')->sole()->processed_at)->not->toBeNull();
});

test('leaving the fake checkout returns the buyer to their order without paying', function () {
    $order = orderAwaitingPayment();
    $payment = $order->latestPayment;

    $response = $this->post('/payments/fake/'.$payment->provider_checkout_id, ['outcome' => 'left']);
    $this->get($response->headers->get('Location'))->assertOk()->assertSeeText('You haven’t paid yet');

    expect(Payment::query()->sole()->status)->toBe(PaymentStatus::Open);
});

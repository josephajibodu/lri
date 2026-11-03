<?php

use App\Enums\CheckoutStep;
use App\Enums\PaymentMethod;
use App\Http\Middleware\IdentifyVisitor;
use App\Models\CheckoutEvent;
use App\Models\Order;
use App\Models\PaymentChannel;
use App\Models\TicketType;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;

uses(RefreshDatabase::class);

beforeEach(function () {
    Mail::fake();
    fakeBachs();

    $this->visitorId = '9d7c3c52-1f0e-4a5e-9a51-3b9a3a0a6f10';
    $this->ticketType = TicketType::factory()->create(['event' => 'disco-run']);
    $this->transfer = PaymentChannel::factory()->method(PaymentMethod::BankTransfer)->featured()->create();
});

function steps(): array
{
    return CheckoutEvent::query()->orderBy('id')->get()->map(fn (CheckoutEvent $event): string => $event->step->value)->all();
}

function placeOrderAsVisitor(array $overrides = []): Order
{
    test()->withCookie(IdentifyVisitor::VisitorCookie, test()->visitorId)->post(route('orders.store'), [
        'ticket_type' => test()->ticketType->slug,
        'quantity' => 1,
        'name' => 'Ada Bello',
        'email' => 'ada@example.com',
        'channel' => test()->transfer->id,
        ...$overrides,
    ]);

    return Order::query()->latest('id')->firstOrFail();
}

test('a first visit gets a visitor id cookie and its page view is noted', function () {
    $this->get(route('disco-run'))->assertOk()->assertCookie(IdentifyVisitor::VisitorCookie);

    $event = CheckoutEvent::query()->sole();

    expect($event->step)->toBe(CheckoutStep::TicketPageViewed)
        ->and($event->visitor_id)->not->toBeNull()
        ->and($event->properties)->toBe(['tickets' => 'on_sale']);
});

test('link previews and crawlers don’t count as page views', function (string $userAgent) {
    $this->withHeader('User-Agent', $userAgent)->get(route('disco-run'))->assertOk();

    expect(CheckoutEvent::query()->count())->toBe(0);
})->with(['WhatsApp/2.23.20.0', 'facebookexternalhit/1.1', 'Googlebot/2.1']);

test('where the visitor came from is credited on their order', function () {
    $this->withCookie(IdentifyVisitor::VisitorCookie, $this->visitorId)
        ->get(route('disco-run', ['utm_source' => 'Instagram', 'utm_medium' => 'story', 'utm_campaign' => 'disco-launch']))
        ->assertCookie(IdentifyVisitor::AttributionCookie);

    expect(CheckoutEvent::query()->sole()->properties['source'])->toBe('instagram');

    $this->withCookie(IdentifyVisitor::VisitorCookie, $this->visitorId)
        ->withCookie(IdentifyVisitor::AttributionCookie, json_encode(['source' => 'instagram', 'medium' => 'story', 'campaign' => 'disco-launch', 'landing' => '/disco-run']))
        ->post(route('orders.store'), [
            'ticket_type' => $this->ticketType->slug,
            'quantity' => 1,
            'name' => 'Ada Bello',
            'email' => 'ada@example.com',
            'channel' => $this->transfer->id,
        ]);

    expect(Order::query()->sole())
        ->visitor_id->toBe($this->visitorId)
        ->attribution->toMatchArray(['source' => 'instagram', 'medium' => 'story', 'campaign' => 'disco-launch']);
});

test('a visit from another site is credited to that site', function () {
    $this->withHeader('Referer', 'https://l.instagram.com/?u=x')->get(route('disco-run'));

    expect(CheckoutEvent::query()->sole()->properties['source'])->toBe('l.instagram.com');
});

test('a paid order leaves the whole journey under one visitor and order', function () {
    $order = placeOrderAsVisitor();
    $payment = $order->latestPayment;

    deliverBachsWebhook(bachsEvent('collection.succeeded', [
        'checkout_id' => $payment->provider_checkout_id,
        'amount' => '10000.00',
        'charge_id' => 'ch_1',
        'status' => 'SUCCEEDED',
        'payment_method_details' => ['type' => 'bank_transfer'],
    ]))->assertOk();
    $this->withCookie(IdentifyVisitor::VisitorCookie, $this->visitorId)->get($order->statusUrl(['from' => 'checkout']));
    $this->withCookie(IdentifyVisitor::VisitorCookie, $this->visitorId)->get($order->statusUrl(['from' => 'checkout']));

    expect(steps())->toBe(['details_submitted', 'method_chosen', 'checkout_opened', 'payment_succeeded', 'returned_from_checkout']);

    $events = CheckoutEvent::query()->get();

    expect($events->pluck('visitor_id')->unique()->all())->toBe([$this->visitorId])
        ->and($events->pluck('order_id')->unique()->all())->toBe([$order->id])
        ->and($events->firstWhere('step', CheckoutStep::MethodChosen)->method)->toBe(PaymentMethod::BankTransfer)
        ->and($events->firstWhere('step', CheckoutStep::MethodChosen)->properties)->toBe(['featured' => true])
        ->and($events->firstWhere('step', CheckoutStep::PaymentSucceeded)->properties)->toHaveKey('minutes_to_pay');
});

test('a failed payment is noted with its reason, and paying again is noted as a comeback', function () {
    $order = placeOrderAsVisitor();
    deliverBachsWebhook(bachsEvent('collection.failed', [
        'checkout_id' => $order->latestPayment->provider_checkout_id,
        'amount' => '10000.00',
        'status' => 'FAILED',
        'reason' => 'Insufficient funds',
    ]))->assertOk();

    $this->withCookie(IdentifyVisitor::VisitorCookie, $this->visitorId)->post($order->signedUrl('orders.pay'), ['channel' => $this->transfer->id]);

    $failed = CheckoutEvent::query()->where('step', CheckoutStep::PaymentFailed)->sole();
    $resumed = CheckoutEvent::query()->where('step', CheckoutStep::CheckoutResumed)->sole();

    expect($failed->properties)->toBe(['reason' => 'insufficient_funds', 'can_retry' => false])
        ->and($resumed->properties)->toBe(['attempt' => 2, 'state' => 'failed']);
});

test('a form sent with mistakes notes which fields, never what was typed', function () {
    $this->withCookie(IdentifyVisitor::VisitorCookie, $this->visitorId)->post(route('orders.store'), [
        'ticket_type' => $this->ticketType->slug,
        'quantity' => 1,
        'name' => 'Ada Bello',
        'email' => 'ada@',
        'phone' => '123',
        'channel' => $this->transfer->id,
    ]);

    $event = CheckoutEvent::query()->sole();

    expect($event->step)->toBe(CheckoutStep::FormInvalid)
        ->and($event->visitor_id)->toBe($this->visitorId)
        ->and($event->properties)->toBe(['fields' => ['email', 'phone']])
        ->and(json_encode($event->properties))->not->toContain('ada');
});

test('a checkout that couldn’t open is noted against the method', function () {
    config()->set('payments.providers.bachs.secret_key', null);

    placeOrderAsVisitor();

    $event = CheckoutEvent::query()->where('step', CheckoutStep::CheckoutUnavailable)->sole();

    expect($event->method)->toBe(PaymentMethod::BankTransfer)
        ->and($event->properties)->toBe(['reason' => 'provider_error']);
});

test('the browser can note starting the form and closing the pop-up checkout on its own order', function () {
    $order = placeOrderAsVisitor();

    $this->withCookie(IdentifyVisitor::VisitorCookie, $this->visitorId)
        ->post(route('checkout-events.store'), ['step' => 'form_started'])
        ->assertNoContent();
    $this->withCookie(IdentifyVisitor::VisitorCookie, $this->visitorId)
        ->post(route('checkout-events.store'), ['step' => 'checkout_closed', 'order' => $order->reference, 'reason' => 'user_closed'])
        ->assertNoContent();

    $closed = CheckoutEvent::query()->where('step', CheckoutStep::CheckoutClosed)->sole();

    expect($closed->order_id)->toBe($order->id)
        ->and($closed->payment_id)->toBe($order->latestPayment->id)
        ->and($closed->properties)->toBe(['reason' => 'user_closed']);
});

test('the browser can’t write into someone else’s order or report server steps', function () {
    $order = placeOrderAsVisitor();

    $this->withCookie(IdentifyVisitor::VisitorCookie, '0b7e7f8e-6a55-4b8e-9a6c-5c0f8d1e2a33')
        ->post(route('checkout-events.store'), ['step' => 'checkout_closed', 'order' => $order->reference])
        ->assertNoContent();
    $this->postJson(route('checkout-events.store'), ['step' => 'payment_succeeded'])->assertUnprocessable();

    expect(CheckoutEvent::query()->where('step', CheckoutStep::CheckoutClosed)->sole()->order_id)->toBeNull()
        ->and(CheckoutEvent::query()->where('step', CheckoutStep::PaymentSucceeded)->exists())->toBeFalse();
});

test('abandoning an order is noted with how many tries it had', function () {
    $order = Order::factory()->for($this->ticketType)->create(['expires_at' => now()->subMinutes(15)]);

    $this->artisan('orders:abandon-unpaid')->assertSuccessful();

    expect(CheckoutEvent::query()->where('step', CheckoutStep::OrderAbandoned)->sole())
        ->order_id->toBe($order->id)
        ->visitor_id->toBe($order->visitor_id)
        ->properties->toBe(['attempts' => 0]);
});

<?php

use App\Enums\PaymentMethod;
use App\Models\Order;
use App\Models\PaymentChannel;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

beforeEach(function () {
    config(['lri.barracks_registration.open' => true]);
});

/**
 * Every rate-limited action on the public site: how many requests a visitor gets a minute, and each route that counts
 * towards that limit. None of the requests has to succeed, because a request is counted before the page behind it runs.
 *
 * @return array<string, array{int, list<array{string, string}>}>
 */
function rateLimitedActions(): array
{
    $order = Order::factory()->create();
    $ticket = ['order' => 'no-such-receipt', 'ticket' => 1];
    $invitation = 'no-such-invitation';

    return [
        'sharing a birthday' => [10, [['POST', route('birthdays.store')]]],
        'registering for the barracks' => [10, [['POST', route('barracks.store')]]],
        'uploading a barracks passport' => [20, [['POST', route('barracks.passport')]]],
        'placing an order' => [30, [['POST', route('orders.store')]]],
        'checking a coupon' => [10, [['POST', route('coupons.check')]]],
        'recording a checkout event' => [60, [['POST', route('checkout-events.store')]]],
        'opening an order' => [60, [['GET', $order->signedUrl('orders.show')]]],
        'checking on an order' => [30, [['GET', $order->signedUrl('orders.status')]]],
        'paying for an order' => [10, [['POST', $order->signedUrl('orders.pay')]]],
        'changing a ticket from the receipt' => [20, [
            ['PUT', route('receipts.tickets.update', $ticket)],
            ['POST', route('receipts.tickets.share-link', $ticket)],
            ['DELETE', route('receipts.tickets.cancel-link', $ticket)],
            ['POST', route('receipts.tickets.reissue', $ticket)],
        ]],
        'claiming a ticket' => [10, [['POST', route('tickets.claim', 'no-such-ticket')]]],
        'opening an invitation' => [30, [
            ['GET', route('invitations.show', $invitation)],
            ['GET', route('invitations.decline-form', $invitation)],
        ]],
        'answering an invitation' => [10, [
            ['POST', route('invitations.accept', $invitation)],
            ['POST', route('invitations.decline', $invitation)],
        ]],
        'receiving a payment webhook' => [240, [['POST', route('webhooks.payments', 'bachs')]]],
    ];
}

test('a buyer can still press Pay now after a minute of their order page checking on the payment', function () {
    fakeBachs();
    $channel = PaymentChannel::factory()->method(PaymentMethod::BankTransfer)->create();
    $order = Order::factory()->create();

    foreach (range(1, 15) as $poll) {
        $this->getJson($order->signedUrl('orders.status'))->assertOk();
    }

    $this->post($order->signedUrl('orders.pay'), ['channel' => $channel->id])
        ->assertRedirectContains('checkout.bachs.io');

    expect($order->payments()->count())->toBe(1);
});

test('each action has its own limit, and a visitor is refused only once they pass it', function () {
    $this->freezeTime();

    foreach (rateLimitedActions() as $action => [$limit, $requests]) {
        foreach (range(0, $limit - 1) as $attempt) {
            [$method, $url] = $requests[$attempt % count($requests)];

            expect($this->call($method, $url)->status())->not->toBe(429, "{$action}: request ".($attempt + 1)." of {$limit} was refused");
        }

        foreach ($requests as [$method, $url]) {
            expect($this->call($method, $url)->status())->toBe(429, "{$action}: {$method} {$url} was still allowed past {$limit} requests");
        }
    }
});

test('a refused visitor can try again a minute later', function () {
    $this->freezeTime();
    $claim = route('tickets.claim', 'no-such-ticket');

    foreach (range(1, 10) as $attempt) {
        $this->post($claim);
    }

    $this->post($claim)->assertTooManyRequests();

    $this->travel(1)->minute();

    $this->post($claim)->assertNotFound();
});

test('one visitor using up a limit does not block another visitor', function () {
    $this->freezeTime();
    $claim = route('tickets.claim', 'no-such-ticket');
    $this->withServerVariables(['REMOTE_ADDR' => '203.0.113.1']);

    foreach (range(1, 10) as $attempt) {
        $this->post($claim);
    }

    $this->post($claim)->assertTooManyRequests();

    $this->withServerVariables(['REMOTE_ADDR' => '203.0.113.2'])->post($claim)->assertNotFound();
});

<?php

use App\Actions\Checkout\RecordPaymentUpdate;
use App\Enums\OrderStatus;
use App\Enums\PaymentStatus;
use App\Mail\TicketsMail;
use App\Models\Order;
use App\Models\Payment;
use App\Models\WebhookCall;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Illuminate\Testing\TestResponse;

uses(RefreshDatabase::class);

beforeEach(function () {
    Mail::fake();
    config()->set('payments.fake', false);
    config()->set('payments.providers.bachs.webhook_secret', 'whsec_test');

    $this->order = Order::factory()->create(['quantity' => 2, 'unit_price' => 1_000_000, 'amount' => 2_000_000]);
    $this->payment = Payment::factory()->for($this->order)->create(['amount' => 2_000_000, 'provider_checkout_id' => 'chk_123']);
});

function bachsEvent(string $type, array $data = [], string $id = 'evt_1'): array
{
    return [
        'id' => $id,
        'type' => $type,
        'created_at' => now()->toIso8601String(),
        'organization_id' => 'acct_1',
        'data' => ['checkout_id' => 'chk_123', 'amount' => '20000.00', 'currency' => 'NGN', ...$data],
    ];
}

function deliverBachsWebhook(array $event, string $secret = 'whsec_test'): TestResponse
{
    $body = json_encode($event);
    $timestamp = now()->getTimestamp();
    $signature = hash_hmac('sha256', "{$timestamp}.{$body}", $secret);

    return test()->call('POST', route('webhooks.payments', 'bachs'), server: [
        'CONTENT_TYPE' => 'application/json',
        'HTTP_X_BACHS_SIGNATURE_V2' => "t={$timestamp},v1={$signature}",
    ], content: $body);
}

test('a signed collection.succeeded webhook pays the order, issues its tickets and emails them', function () {
    deliverBachsWebhook(bachsEvent('collection.succeeded', ['charge_id' => 'ch_1', 'status' => 'SUCCEEDED', 'payment_method_details' => ['type' => 'bank_transfer']]))
        ->assertOk();

    expect($this->order->fresh())
        ->status->toBe(OrderStatus::Paid)
        ->tickets_emailed_at->not->toBeNull()
        ->and($this->order->tickets()->count())->toBe(2)
        ->and($this->payment->fresh()->provider_payment_id)->toBe('ch_1')
        ->and(WebhookCall::query()->sole())
        ->event_id->toBe('evt_1')
        ->type->toBe('collection.succeeded')
        ->processed_at->not->toBeNull();

    Mail::assertSent(TicketsMail::class, fn (TicketsMail $mail): bool => $mail->hasTo($this->order->buyer_email));
});

test('the same webhook sent twice is only handled once', function () {
    $event = bachsEvent('collection.succeeded', ['status' => 'SUCCEEDED']);

    deliverBachsWebhook($event)->assertOk();
    deliverBachsWebhook($event)->assertOk()->assertSeeText('Already handled');

    expect(WebhookCall::query()->count())->toBe(1)
        ->and($this->order->tickets()->count())->toBe(2);

    Mail::assertSentCount(1);
});

test('checkout.completed and collection.succeeded together fulfil the order once, in either order', function () {
    deliverBachsWebhook(bachsEvent('checkout.completed', ['payment_status' => 'paid', 'charge' => ['id' => 'ch_1']], id: 'evt_a'))->assertOk();
    deliverBachsWebhook(bachsEvent('collection.succeeded', ['status' => 'SUCCEEDED'], id: 'evt_b'))->assertOk();

    expect($this->order->tickets()->count())->toBe(2);
    Mail::assertSentCount(1);
});

test('a failure that arrives after the payment succeeded doesn’t undo it', function () {
    deliverBachsWebhook(bachsEvent('collection.succeeded', ['status' => 'SUCCEEDED'], id: 'evt_a'))->assertOk();
    deliverBachsWebhook(bachsEvent('collection.failed', ['status' => 'FAILED', 'reason' => 'Declined'], id: 'evt_b'))->assertOk();

    expect($this->payment->fresh()->status)->toBe(PaymentStatus::Succeeded)
        ->and($this->order->fresh()->status)->toBe(OrderStatus::Paid);
});

test('a webhook whose signature doesn’t check out is refused and not stored', function () {
    deliverBachsWebhook(bachsEvent('collection.succeeded', ['status' => 'SUCCEEDED']), secret: 'whsec_forged')
        ->assertStatus(400);

    expect(WebhookCall::query()->count())->toBe(0)
        ->and($this->order->fresh()->status)->toBe(OrderStatus::Pending);
});

test('an unknown provider is not found', function () {
    $this->post('/webhooks/payments/paystack')->assertNotFound();
});

test('an event for a payment the site doesn’t know is noted and acknowledged', function () {
    deliverBachsWebhook(bachsEvent('collection.succeeded', ['checkout_id' => 'chk_other', 'reference' => 'LRI-NOPE00-AAAA', 'status' => 'SUCCEEDED']))
        ->assertOk();

    expect(WebhookCall::query()->sole())
        ->processed_at->not->toBeNull()
        ->error->toBe('No payment on the site matches this event.');
});

test('a payment is found by our reference when the checkout id is missing', function () {
    deliverBachsWebhook(bachsEvent('collection.succeeded', ['checkout_id' => null, 'reference' => $this->payment->reference, 'status' => 'SUCCEEDED']))
        ->assertOk();

    expect($this->order->fresh()->status)->toBe(OrderStatus::Paid);
});

test('events the site doesn’t act on are stored and acknowledged', function () {
    deliverBachsWebhook(['id' => 'evt_1', 'type' => 'customer.created', 'data' => []])->assertOk();

    expect(WebhookCall::query()->sole())->processed_at->not->toBeNull()->error->toBeNull();
});

test('when handling fails the provider is asked to retry, and the stored call can be replayed after a fix', function () {
    $this->mock(RecordPaymentUpdate::class)->shouldReceive('handle')->once()->andThrow(new RuntimeException('Database is down'));

    deliverBachsWebhook(bachsEvent('collection.succeeded', ['status' => 'SUCCEEDED']))->assertStatus(500);

    $call = WebhookCall::query()->sole();

    expect($call)->processed_at->toBeNull()->failed_at->not->toBeNull()->error->toBe('Database is down');

    $this->app->forgetInstance(RecordPaymentUpdate::class);
    $this->app->offsetUnset(RecordPaymentUpdate::class);

    $this->artisan('payments:replay-webhook', ['call' => $call->id])->assertSuccessful();

    expect($call->fresh())->processed_at->not->toBeNull()->failed_at->toBeNull()
        ->and($this->order->fresh()->status)->toBe(OrderStatus::Paid);
});

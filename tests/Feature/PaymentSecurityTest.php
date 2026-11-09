<?php

use App\Actions\Checkout\PlaceOrder;
use App\Actions\Checkout\RecordPaymentUpdate;
use App\Actions\Checkout\StartPayment;
use App\Enums\CheckoutStep;
use App\Enums\OrderStatus;
use App\Enums\PaymentMethod;
use App\Enums\PaymentStatus;
use App\Exceptions\CheckoutUnavailable;
use App\Exceptions\TicketsUnavailable;
use App\Filament\Resources\Orders\Pages\ListOrders;
use App\Filament\Resources\Orders\Pages\ViewOrder;
use App\Mail\TicketMail;
use App\Mail\TicketsMail;
use App\Models\CheckoutEvent;
use App\Models\Coupon;
use App\Models\Order;
use App\Models\Payment;
use App\Models\PaymentChannel;
use App\Models\Ticket;
use App\Models\TicketType;
use App\Models\User;
use App\Models\WebhookCall;
use App\Payments\Data\PaymentUpdate;
use Illuminate\Foundation\Http\Middleware\PreventRequestForgery;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\Factory as HttpFactory;
use Illuminate\Http\Client\Request as ClientRequest;
use Illuminate\Support\Facades\Exceptions;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Sleep;
use Livewire\Livewire;

uses(RefreshDatabase::class);

/*
 * Attempts to get tickets without paying for them, or to stop other people buying. Each test tries the attack and
 * states the safe outcome.
 */

beforeEach(function () {
    Mail::fake();
    fakeBachs();

    $this->ticketType = TicketType::factory()->create(['slug' => 'disco-run-standard', 'price' => 1_000_000, 'max_per_order' => 10]);
    $this->channel = PaymentChannel::factory()->method(PaymentMethod::BankTransfer)->featured()->create();
});

function attackForm(array $overrides = []): array
{
    return [
        'ticket_type' => 'disco-run-standard',
        'quantity' => 1,
        'name' => 'Ada Bello',
        'email' => 'ada@example.com',
        'phone' => '',
        'channel' => test()->channel->id,
        ...$overrides,
    ];
}

function placeAttackOrder(int $quantity = 1, ?string $coupon = null): Order
{
    return app(PlaceOrder::class)->handle(
        test()->ticketType,
        $quantity,
        ['name' => 'Ada Bello', 'email' => 'buyer'.fake()->unique()->randomNumber(6).'@example.com', 'phone' => null, 'marketing_consent' => false],
        couponCode: $coupon,
    );
}

function openAttackPayment(Order $order): Payment
{
    return app(StartPayment::class)->handle($order, test()->channel)->payment;
}

/**
 * The event Bachs sends when a charge lands, as its sandbox really sends it.
 *
 * @return array<string, mixed>
 */
function chargeLanded(Payment $payment, array $data = []): array
{
    return bachsEvent('collection.succeeded', [
        'checkout_id' => $payment->provider_checkout_id,
        'charge_id' => 'ch_'.$payment->id,
        'reference' => $payment->reference,
        'status' => 'SUCCEEDED',
        'amount' => $payment->money()->toDecimal(),
        'currency' => $payment->currency,
        'processing_fee' => '150.00',
        'processing_fee_currency' => $payment->currency,
        ...$data,
    ], id: 'evt_paid_'.$payment->id);
}

function paidAttackOrder(int $quantity = 1, ?string $coupon = null): Order
{
    $order = placeAttackOrder($quantity, $coupon);

    deliverBachsWebhook(chargeLanded(openAttackPayment($order)))->assertOk();

    return $order->fresh();
}

/**
 * The event Bachs sends once a refund reaches the buyer. Its reference is the refund's own, not the payment's.
 *
 * @return array<string, mixed>
 */
function refundPaid(Payment $payment, string $amount, string $id = 'evt_refund'): array
{
    return [
        'id' => $id, 'type' => 'refund.paid', 'created_at' => now()->toIso8601String(), 'organization_id' => 'acct_1',
        'data' => [
            'refund_id' => 'rf_1', 'charge_id' => $payment->provider_payment_id, 'reference' => 'refund-'.$payment->id, 'status' => 'success',
            'requested_amount' => $amount, 'refunded_amount' => $amount, 'refund_fee_amount' => '0.00', 'fee_bearer' => 'merchant', 'reason' => null,
        ],
    ];
}

/**
 * The event Bachs sends when a dispute opens or changes.
 *
 * @return array<string, mixed>
 */
function disputeEvent(Payment $payment, string $status, ?string $amount = null, string $type = 'dispute.updated'): array
{
    return [
        'id' => 'evt_dispute_'.$status, 'type' => $type, 'created_at' => now()->toIso8601String(), 'organization_id' => 'acct_1',
        'data' => [
            'dispute_id' => 'dsp_1', 'charge_id' => $payment->provider_payment_id, 'amount' => $amount ?? $payment->money()->toDecimal(), 'currency' => $payment->currency,
            'status' => $status, 'is_response_editable' => true, 'reason' => 'fraudulent', 'charge_amount' => $payment->money()->toDecimal(), 'charge_currency' => $payment->currency,
        ],
    ];
}

/* ---------------------------------------------------------------- Getting tickets without paying */

test('coming back from checkout does not pay an order the provider says is still open', function () {
    $order = placeAttackOrder();
    $payment = openAttackPayment($order);
    Http::fake(['sandbox-api.bachs.io/v1/checkout-sessions/*' => Http::response(bachsCheckoutSession($payment, paid: false))]);

    $this->get($order->statusUrl(['from' => 'checkout']))->assertOk();

    expect($order->fresh()->status)->toBe(OrderStatus::Pending)
        ->and(Ticket::query()->count())->toBe(0);
});

test('order links cannot be forged, edited or reused on another order or route', function () {
    $mine = placeAttackOrder();
    $theirs = placeAttackOrder();

    $this->get('/orders/'.$mine->reference)->assertForbidden();
    $this->get($mine->statusUrl().'&from=checkout')->assertForbidden();
    $this->get(str_replace($mine->reference, $theirs->reference, $mine->statusUrl()))->assertForbidden();
    $this->get(str_replace('/orders/'.$mine->reference, '/orders/'.$mine->reference.'/status', $mine->statusUrl()))->assertForbidden();
    $this->post(str_replace('/orders/'.$mine->reference, '/orders/'.$mine->reference.'/payments', $mine->statusUrl()), ['channel' => $this->channel->id])->assertForbidden();
    $this->post('/orders/'.$mine->reference.'/payments', ['channel' => $this->channel->id])->assertForbidden();

    expect(Payment::query()->count())->toBe(0);
});

test('forged webhooks are refused and pay nothing', function () {
    $order = placeAttackOrder();
    $body = json_encode(chargeLanded(openAttackPayment($order)));
    $now = now()->getTimestamp();
    $sign = fn (int $time, string $content, string $secret): string => hash_hmac('sha256', "{$time}.{$content}", $secret);
    $send = fn (array $headers) => $this->call('POST', route('webhooks.payments', 'bachs'), server: ['CONTENT_TYPE' => 'application/json', ...$headers], content: $body);

    $forgeries = [
        'no signature' => [],
        'wrong secret' => ['HTTP_X_BACHS_SIGNATURE_V2' => "t={$now},v1=".$sign($now, $body, 'guess')],
        'empty secret' => ['HTTP_X_BACHS_SIGNATURE_V2' => "t={$now},v1=".$sign($now, $body, '')],
        'an old one, replayed' => ['HTTP_X_BACHS_SIGNATURE_V2' => 't='.($now - 600).',v1='.$sign($now - 600, $body, 'whsec_test')],
        'from the future' => ['HTTP_X_BACHS_SIGNATURE_V2' => 't='.($now + 600).',v1='.$sign($now + 600, $body, 'whsec_test')],
        'body changed after signing' => ['HTTP_X_BACHS_SIGNATURE_V2' => "t={$now},v1=".$sign($now, '{"type":"ping"}', 'whsec_test')],
        'signature with no timestamp' => ['HTTP_X_BACHS_SIGNATURE_V2' => 'v1='.$sign($now, $body, 'whsec_test')],
        'empty signature' => ['HTTP_X_BACHS_SIGNATURE_V2' => "t={$now},v1="],
        'older header, wrong secret' => ['HTTP_X_BACHS_TIMESTAMP' => (string) $now, 'HTTP_X_BACHS_SIGNATURE' => $sign($now, $body, 'guess')],
    ];

    foreach ($forgeries as $name => $headers) {
        expect($send($headers)->getStatusCode())->toBe(400, "{$name} was not refused");
    }

    expect($order->fresh()->status)->toBe(OrderStatus::Pending)
        ->and(Ticket::query()->count())->toBe(0)
        ->and(WebhookCall::query()->count())->toBe(0);
});

test('a site with no webhook secret set accepts no webhooks at all', function () {
    config()->set('payments.providers.bachs.webhook_secret', '');
    $order = placeAttackOrder();
    $body = json_encode(chargeLanded(openAttackPayment($order)));
    $now = now()->getTimestamp();

    $this->call('POST', route('webhooks.payments', 'bachs'), server: [
        'CONTENT_TYPE' => 'application/json',
        'HTTP_X_BACHS_SIGNATURE_V2' => "t={$now},v1=".hash_hmac('sha256', "{$now}.{$body}", ''),
    ], content: $body)->assertStatus(400);

    expect(Ticket::query()->count())->toBe(0);
});

test('the buyer cannot set their own price, status or discount', function () {
    $coupon = Coupon::factory()->create(['discount_value' => 99]);

    $this->post(route('orders.store'), attackForm([
        'amount' => 1, 'unit_price' => 1, 'discount_amount' => 999_999, 'status' => 'paid', 'paid_at' => now()->toDateTimeString(),
        'coupon_id' => $coupon->id, 'receipt_token' => 'mine', 'reference' => 'LRI-HACKED', 'currency' => 'USD', 'expires_at' => now()->addYear()->toDateTimeString(),
        'review_note' => 'cleared', 'ip_address' => '1.1.1.1',
    ]));

    expect(Order::query()->sole())
        ->amount->toBe(1_000_000)
        ->unit_price->toBe(1_000_000)
        ->discount_amount->toBe(0)
        ->coupon_id->toBeNull()
        ->status->toBe(OrderStatus::Pending)
        ->currency->toBe('NGN')
        ->reference->not->toBe('LRI-HACKED')
        ->receipt_token->not->toBe('mine')
        ->review_note->toBeNull()
        ->ip_address->toBe('127.0.0.1');
});

test('strange quantities are refused', function (mixed $quantity) {
    $this->post(route('orders.store'), attackForm(['quantity' => $quantity]))->assertSessionHasErrors('quantity');

    expect(Order::query()->count())->toBe(0);
})->with([
    'zero' => [0], 'negative' => [-1], 'decimal' => [1.5], 'over the limit' => [11], 'scientific' => ['1e1'],
    'a list' => [['1']], 'empty' => [''], 'words' => ['abc'], 'hex' => ['0x1'], 'huge' => [PHP_INT_MAX],
]);

test('a field sent as a list is refused, not a crash', function (string $field) {
    $this->post(route('orders.store'), attackForm([$field => ['a', 'b']]))->assertSessionHasErrors($field);

    expect(Order::query()->count())->toBe(0);
})->with(['name', 'email', 'phone', 'coupon', 'channel', 'ticket_type']);

test('tickets that are not on sale cannot be ordered', function (array $state) {
    $this->ticketType->update($state);

    $this->post(route('orders.store'), attackForm())->assertSessionHasErrors('quantity');

    expect(Order::query()->count())->toBe(0);
})->with([
    'switched off' => [['is_active' => false]],
    'not open yet' => fn () => ['sales_start_at' => now()->addDay()],
    'closed' => fn () => ['sales_end_at' => now()->subDay()],
]);

test('coupons that should not work do not work', function (Closure $makeCoupon, mixed $typed = null) {
    $coupon = $makeCoupon();

    $this->post(route('orders.store'), attackForm(['coupon' => $typed ?? $coupon->code]))->assertSessionHasErrors('coupon');

    expect(Order::query()->where('discount_amount', '>', 0)->count())->toBe(0);
})->with([
    'disabled' => [fn () => Coupon::factory()->disabled()->create()],
    'ended' => [fn () => Coupon::factory()->create(['ends_at' => now()->subMinute()])],
    'not started' => [fn () => Coupon::factory()->create(['starts_at' => now()->addHour()])],
    'a wildcard, %' => [fn () => Coupon::factory()->create(), '%'],
    'a wildcard, _' => [fn () => Coupon::factory()->create(), '________'],
    'database code' => [fn () => Coupon::factory()->create(), "' OR '1'='1"],
    'two codes at once' => [fn () => Coupon::factory()->create(), ['A', 'B']],
]);

test('a paid order cannot be paid again, and neither can a refunded or disputed one', function (OrderStatus $status) {
    $order = paidAttackOrder();
    $order->update(['status' => $status]);

    expect(fn () => app(StartPayment::class)->handle($order, $this->channel))->toThrow(CheckoutUnavailable::class);
    expect($order->payments()->count())->toBe(1);
})->with([OrderStatus::Paid, OrderStatus::Refunded, OrderStatus::Disputed]);

test('a hold that lapsed cannot be revived when the tickets are gone', function () {
    $this->ticketType->update(['capacity' => 1]);
    $order = placeAttackOrder();
    $this->travel(2)->hours();
    placeAttackOrder();

    expect(fn () => app(StartPayment::class)->handle($order->fresh(), $this->channel))->toThrow(TicketsUnavailable::class);
});

test('logs stay closed to guests and to desk staff', function () {
    $this->get('/log-viewer')->assertRedirect();
    expect($this->getJson('/log-viewer/api/files')->getStatusCode())->toBeIn([401, 403]);

    $this->actingAs(User::factory()->deskStaff()->create());

    $this->get('/log-viewer')->assertForbidden();
    $this->getJson('/log-viewer/api/files')->assertForbidden();
});

test('a script typed as a name does not run on the admin’s orders page', function () {
    $script = '<script>alert(1)</script>';
    Order::factory()->paid()->create(['buyer_name' => $script]);

    $this->actingAs(User::factory()->superAdmin()->create())->get('/admin/orders')->assertOk()->assertDontSee($script, false);
});

/* ---------------------------------------------------------------- Paying less than the price */

test('a short, zero, negative or wrong-currency payment issues no tickets', function (array $data) {
    $order = placeAttackOrder();
    $payment = openAttackPayment($order);

    deliverBachsWebhook(chargeLanded($payment, $data))->assertOk();

    expect($order->fresh()->status)->toBe(OrderStatus::Pending)
        ->and($payment->fresh()->status)->toBe(PaymentStatus::Underpaid)
        ->and(Ticket::query()->count())->toBe(0);
})->with([
    'one naira short' => [['amount' => '9999.00']],
    'one kobo short' => [['amount' => '9999.99']],
    'zero' => [['amount' => '0.00']],
    'negative' => [['amount' => '-10000.00']],
    'another currency' => [['currency' => 'USD']],
]);

test('a signed event about an unknown checkout, or a free checkout, issues no tickets', function () {
    $order = placeAttackOrder();
    $payment = openAttackPayment($order);

    deliverBachsWebhook(bachsEvent('collection.succeeded', ['checkout_id' => 'chk_other', 'reference' => 'NOT-OURS', 'charge_id' => 'ch_other', 'status' => 'SUCCEEDED'], id: 'evt_a'))->assertOk();
    deliverBachsWebhook(bachsEvent('checkout.completed', ['checkout_id' => $payment->provider_checkout_id, 'payment_status' => 'no_payment_required', 'amount' => '0'], id: 'evt_b'))->assertOk();

    expect($order->fresh()->status)->toBe(OrderStatus::Pending)
        ->and(Ticket::query()->count())->toBe(0);
});

test('repeating a paid event, even under a new id, never issues extra tickets', function () {
    $order = paidAttackOrder(2);
    $payment = $order->payments()->sole();

    deliverBachsWebhook(chargeLanded($payment))->assertOk();
    deliverBachsWebhook([...chargeLanded($payment), 'id' => 'evt_new'])->assertOk();
    deliverBachsWebhook(bachsEvent('checkout.completed', ['checkout_id' => $payment->provider_checkout_id, 'payment_status' => 'paid', 'amount' => $payment->money()->toDecimal()], id: 'evt_new2'))->assertOk();

    expect($order->tickets()->count())->toBe(2);
});

test('a success that doesn’t say how much arrived issues no tickets, and is flagged', function () {
    $order = placeAttackOrder();
    $payment = openAttackPayment($order);
    $event = chargeLanded($payment);
    unset($event['data']['amount'], $event['data']['currency']);

    deliverBachsWebhook($event)->assertOk();

    expect(Ticket::query()->count())->toBe(0)
        ->and($payment->fresh()->status)->toBe(PaymentStatus::Open)
        ->and($order->fresh()->review_note)->toContain('without saying how much arrived');
});

test('a success with no amount is settled when the provider is asked directly', function () {
    $order = placeAttackOrder();
    $payment = openAttackPayment($order);
    $event = chargeLanded($payment);
    unset($event['data']['amount'], $event['data']['currency']);
    deliverBachsWebhook($event)->assertOk();

    fakePaidBachsCheckout($payment);
    $this->travel(10)->minutes();
    $this->artisan('payments:reconcile')->assertSuccessful();

    expect($order->fresh()->status)->toBe(OrderStatus::Paid);
});

test('a payment "accepted" at less than the price issues no tickets', function () {
    $order = placeAttackOrder();
    $payment = openAttackPayment($order);

    deliverBachsWebhook(chargeLanded($payment, ['status' => 'ACCEPTED', 'expected_amount' => '10000.00', 'received_amount' => '1000.00']))->assertOk();

    expect(Ticket::query()->count())->toBe(0)
        ->and($payment->fresh()->status)->toBe(PaymentStatus::Underpaid);
});

test('a payment "accepted" without saying what arrived isn’t believed, however much it says was charged', function () {
    $order = placeAttackOrder();
    $payment = openAttackPayment($order);

    deliverBachsWebhook(chargeLanded($payment, ['status' => 'ACCEPTED']))->assertOk();

    expect(Ticket::query()->count())->toBe(0)
        ->and($payment->fresh()->status)->toBe(PaymentStatus::Open);
});

test('an overpayment still counts as paid', function () {
    $order = placeAttackOrder();
    $payment = openAttackPayment($order);

    deliverBachsWebhook(chargeLanded($payment, ['status' => 'OVERPAID', 'expected_amount' => '10000.00', 'received_amount' => '12000.00', 'overpaid_amount' => '2000.00']))->assertOk();

    expect($order->fresh()->status)->toBe(OrderStatus::Paid);
});

test('checkout.completed is judged on the money charged, not what the checkout asked for', function () {
    $order = placeAttackOrder();
    $payment = openAttackPayment($order);

    deliverBachsWebhook(bachsEvent('checkout.completed', [
        'checkout_id' => $payment->provider_checkout_id, 'payment_status' => 'paid', 'amount' => '10000.00', 'currency' => 'NGN',
        'charge' => ['id' => 'ch_1', 'amount' => '1000.00', 'currency' => 'NGN', 'status' => 'accepted'],
    ], id: 'evt_short'))->assertOk();

    expect(Ticket::query()->count())->toBe(0)
        ->and($payment->fresh()->status)->toBe(PaymentStatus::Underpaid);
});

test('asking the provider directly is judged on what was paid, not what the checkout asked for', function (array $charge) {
    $order = placeAttackOrder();
    $payment = openAttackPayment($order);
    $session = bachsCheckoutSession($payment);
    $session['charge'] = [...$session['charge'], ...$charge];
    Http::fake(['sandbox-api.bachs.io/v1/checkout-sessions/*' => Http::response($session)]);

    $this->get($order->statusUrl(['from' => 'checkout']));

    expect(Ticket::query()->count())->toBe(0);
})->with([
    'accepted, short' => [['status' => 'accepted', 'amount' => '10000.00', 'amount_paid' => '1000.00', 'currency' => 'NGN']],
    'accepted, says nothing' => [['status' => 'accepted']],
    'succeeded, short' => [['status' => 'succeeded', 'amount' => '10000.00', 'amount_paid' => '1000.00', 'currency' => 'NGN']],
]);

test('a live site refuses to open a checkout with a sandbox key', function () {
    app()->detectEnvironment(fn (): string => 'production');

    $response = $this->withoutMiddleware(PreventRequestForgery::class)->post(route('orders.store'), attackForm());

    expect($response->getStatusCode())->not->toBe(419)
        ->and(Payment::query()->whereNotNull('provider_checkout_id')->count())->toBe(0);
    Http::assertNothingSent();
});

test('a live site refuses a live key pointed at the sandbox', function () {
    app()->detectEnvironment(fn (): string => 'production');
    config()->set('payments.providers.bachs.secret_key', 'sk_live_test');
    config()->set('payments.providers.bachs.base_url', 'https://sandbox-api.bachs.io');

    $this->withoutMiddleware(PreventRequestForgery::class)->post(route('orders.store'), attackForm());

    Http::assertNothingSent();
});

test('a live site with a live key opens its checkout at the live address', function () {
    app()->detectEnvironment(fn (): string => 'production');
    config()->set('payments.providers.bachs.secret_key', 'sk_live_test');
    Http::fake(['api.bachs.io/v1/checkout-sessions' => fn (ClientRequest $request) => Http::response(['checkout_id' => 'chk_live', 'checkout_url' => 'https://checkout.bachs.io/c/chk_live'], 201)]);

    $this->withoutMiddleware(PreventRequestForgery::class)->post(route('orders.store'), attackForm())->assertRedirect('https://checkout.bachs.io/c/chk_live');
});

/* ---------------------------------------------------------------- Getting the money back and keeping the tickets */

test('a full refund cancels the tickets and gives back the seat and coupon use', function () {
    $this->ticketType->update(['capacity' => 5]);
    $coupon = Coupon::factory()->create(['discount_value' => 50, 'max_orders' => 1]);
    $order = paidAttackOrder(2, $coupon->code);
    $payment = $order->payments()->sole();

    deliverBachsWebhook(refundPaid($payment, $payment->money()->toDecimal()))->assertOk();

    expect($order->fresh())
        ->status->toBe(OrderStatus::Refunded)
        ->refunded_at->not->toBeNull()
        ->and($payment->fresh()->status)->toBe(PaymentStatus::Refunded)
        ->and($order->tickets()->first()->isValid())->toBeFalse()
        ->and($this->ticketType->fresh()->ticketsLeft())->toBe(5)
        ->and($coupon->fresh()->timesTaken())->toBe(0);

    $this->get($order->tickets()->first()->claimUrl())->assertNotFound();
    $this->get($order->fresh()->statusUrl())->assertOk()->assertSeeText('refunded');
});

test('getting everything back except Bachs’s fee counts as a full refund', function () {
    $order = paidAttackOrder();
    $payment = $order->payments()->sole();

    deliverBachsWebhook(refundPaid($payment, '9850.00'))->assertOk();

    expect($order->fresh()->status)->toBe(OrderStatus::Refunded);
});

test('a refund that doesn’t say how much counts as a full one', function () {
    $order = paidAttackOrder();
    $payment = $order->payments()->sole();
    $event = refundPaid($payment, '0.00');
    unset($event['data']['refunded_amount'], $event['data']['requested_amount']);

    deliverBachsWebhook($event)->assertOk();

    expect($order->fresh()->status)->toBe(OrderStatus::Refunded);
});

test('the provider saying a payment was refunded also stops the order being paid', function () {
    $order = paidAttackOrder();
    $payment = $order->payments()->sole();

    app(RecordPaymentUpdate::class)->handle($payment, new PaymentUpdate(status: PaymentStatus::Refunded, providerStatus: 'refunded'));

    expect($order->fresh()->status)->toBe(OrderStatus::Refunded);
});

test('a partial refund keeps the tickets working and flags the order', function () {
    $order = paidAttackOrder(2);
    $payment = $order->payments()->sole();

    deliverBachsWebhook(refundPaid($payment, '10000.00'))->assertOk();

    expect($order->fresh())
        ->status->toBe(OrderStatus::Paid)
        ->review_note->toContain('Part of payment')
        ->and($payment->fresh()->status)->toBe(PaymentStatus::PartiallyRefunded)
        ->and($order->tickets()->first()->isValid())->toBeTrue();
});

test('refunding the extra payment on an order paid twice keeps its tickets', function () {
    $order = placeAttackOrder();
    $first = openAttackPayment($order);
    $second = openAttackPayment($order->fresh());
    deliverBachsWebhook(chargeLanded($first))->assertOk();
    deliverBachsWebhook(chargeLanded($second))->assertOk();

    expect($order->fresh()->review_note)->toContain('paid for twice');

    deliverBachsWebhook(refundPaid($second->fresh(), $second->money()->toDecimal()))->assertOk();

    expect($order->fresh()->status)->toBe(OrderStatus::Paid)
        ->and($order->tickets()->first()->isValid())->toBeTrue();

    deliverBachsWebhook(refundPaid($first->fresh(), $first->money()->toDecimal(), id: 'evt_refund_2'))->assertOk();

    expect($order->fresh()->status)->toBe(OrderStatus::Refunded);
});

test('money landing after a refund brings back the tickets it already has, not new ones, and is flagged', function () {
    $order = placeAttackOrder(2);
    $first = openAttackPayment($order);
    $second = openAttackPayment($order->fresh());
    deliverBachsWebhook(chargeLanded($first))->assertOk();
    deliverBachsWebhook(refundPaid($first->fresh(), $first->money()->toDecimal()))->assertOk();

    deliverBachsWebhook(chargeLanded($second))->assertOk();

    expect($order->fresh())
        ->status->toBe(OrderStatus::Paid)
        ->refunded_at->toBeNull()
        ->review_note->toContain('after this order was refunded')
        ->and($order->tickets()->count())->toBe(2);
});

test('an old success replayed after a refund doesn’t bring the tickets back', function () {
    $order = paidAttackOrder();
    $payment = $order->payments()->sole();
    deliverBachsWebhook(refundPaid($payment, $payment->money()->toDecimal()))->assertOk();

    deliverBachsWebhook([...chargeLanded($payment), 'id' => 'evt_replayed'])->assertOk();

    expect($order->fresh()->status)->toBe(OrderStatus::Refunded);
});

test('a refund after a headset was handed out is flagged', function () {
    $order = paidAttackOrder();
    $payment = $order->payments()->sole();
    $order->tickets()->first()->update(['checked_in_at' => now()]);

    deliverBachsWebhook(refundPaid($payment, $payment->money()->toDecimal()))->assertOk();

    expect($order->fresh()->review_note)->toContain('after 1 of its headsets');
});

test('a refund for a charge the site doesn’t know is noted on the webhook', function () {
    deliverBachsWebhook([
        'id' => 'evt_unknown', 'type' => 'refund.paid', 'created_at' => now()->toIso8601String(),
        'data' => ['refund_id' => 'rf_1', 'charge_id' => 'ch_unknown', 'reference' => 'r', 'status' => 'success', 'refunded_amount' => '100.00'],
    ])->assertOk();

    expect(WebhookCall::query()->sole()->error)->toContain('No payment on the site matches');
});

test('a chargeback puts the tickets on hold straight away, but keeps their seats', function () {
    $this->ticketType->update(['capacity' => 5]);
    $order = paidAttackOrder(2);
    $payment = $order->payments()->sole();

    deliverBachsWebhook(disputeEvent($payment, 'needs_response', type: 'dispute.created'))->assertOk();

    expect($order->fresh())
        ->status->toBe(OrderStatus::Disputed)
        ->review_note->toContain('disputing payment')
        ->and($payment->fresh()->status)->toBe(PaymentStatus::Disputed)
        ->and($order->tickets()->first()->isValid())->toBeFalse()
        ->and($this->ticketType->fresh()->ticketsLeft())->toBe(3);

    $this->get($order->tickets()->first()->claimUrl())->assertNotFound();
    $this->get($order->fresh()->receiptUrl())->assertRedirect();
    $this->get($order->fresh()->statusUrl())->assertOk()->assertSeeText('being questioned');
});

test('winning a chargeback makes the tickets work again, with nothing new issued', function () {
    $order = paidAttackOrder(2);
    $payment = $order->payments()->sole();
    deliverBachsWebhook(disputeEvent($payment, 'needs_response', type: 'dispute.created'))->assertOk();

    deliverBachsWebhook(disputeEvent($payment, 'won'))->assertOk();

    expect($order->fresh()->status)->toBe(OrderStatus::Paid)
        ->and($payment->fresh()->status)->toBe(PaymentStatus::Succeeded)
        ->and($order->tickets()->count())->toBe(2);
    Mail::assertSent(TicketsMail::class, 1);
});

test('losing a chargeback cancels the tickets', function () {
    $order = paidAttackOrder();
    $payment = $order->payments()->sole();
    deliverBachsWebhook(disputeEvent($payment, 'needs_response', type: 'dispute.created'))->assertOk();

    deliverBachsWebhook(disputeEvent($payment, 'lost'))->assertOk();

    expect($order->fresh()->status)->toBe(OrderStatus::Refunded);
});

test('losing a chargeback over part of a payment keeps the tickets and flags the order', function () {
    $order = paidAttackOrder(2);
    $payment = $order->payments()->sole();
    deliverBachsWebhook(disputeEvent($payment, 'needs_response', type: 'dispute.created'))->assertOk();

    deliverBachsWebhook(disputeEvent($payment, 'lost', amount: '10000.00'))->assertOk();

    expect($order->fresh())->status->toBe(OrderStatus::Paid)->review_note->toContain('Part of payment');
});

test('a dispute that closes without a ruling stays on hold for an organiser', function () {
    $order = paidAttackOrder();
    $payment = $order->payments()->sole();
    deliverBachsWebhook(disputeEvent($payment, 'needs_response', type: 'dispute.created'))->assertOk();

    deliverBachsWebhook(disputeEvent($payment, 'closed'))->assertOk();

    expect($order->fresh())->status->toBe(OrderStatus::Disputed)->review_note->toContain('dispute:closed');
});

test('a disputed payment isn’t cleared by replaying its old success', function () {
    $order = paidAttackOrder();
    $payment = $order->payments()->sole();
    deliverBachsWebhook(disputeEvent($payment, 'needs_response', type: 'dispute.created'))->assertOk();

    deliverBachsWebhook([...chargeLanded($payment), 'id' => 'evt_replayed'])->assertOk();

    expect($order->fresh()->status)->toBe(OrderStatus::Disputed);
});

test('an organiser can release disputed tickets, with a note saying who did', function () {
    $order = paidAttackOrder();
    $payment = $order->payments()->sole();
    deliverBachsWebhook(disputeEvent($payment, 'closed', type: 'dispute.created'))->assertOk();
    $this->actingAs(User::factory()->superAdmin()->create(['name' => 'Kemi']));

    Livewire::test(ViewOrder::class, ['record' => $order->id])->callAction('releaseTickets');

    expect($order->fresh())->status->toBe(OrderStatus::Paid)->review_note->toContain('released from their dispute by Kemi');
});

test('an organiser can clear an order’s note, and flagged orders can be found', function () {
    $flagged = Order::factory()->paid()->create(['review_note' => 'Look at this.']);
    $other = Order::factory()->paid()->create();
    $this->actingAs(User::factory()->superAdmin()->create());

    Livewire::test(ListOrders::class)
        ->filterTable('needs_look', true)
        ->assertCanSeeTableRecords([$flagged])
        ->assertCanNotSeeTableRecords([$other]);

    Livewire::test(ViewOrder::class, ['record' => $flagged->id])
        ->assertSeeText('Look at this.')
        ->callAction('markLookedAt');

    expect($flagged->fresh()->review_note)->toBeNull();
});

/* ---------------------------------------------------------------- Holding tickets without paying */

test('one connection cannot hold every ticket without paying, and everyone else can still buy', function () {
    $this->ticketType->update(['capacity' => 100]);

    foreach (range(1, 10) as $try) {
        $this->post(route('orders.store'), attackForm(['quantity' => 10, 'email' => "fake{$try}@example.com"]));
    }

    expect($this->ticketType->fresh()->ticketsLeft())->toBe(60);

    $this->withServerVariables(['REMOTE_ADDR' => '5.5.5.5'])
        ->post(route('orders.store'), attackForm(['quantity' => 10, 'email' => 'real@example.com']))
        ->assertRedirect();

    expect($this->ticketType->fresh()->ticketsLeft())->toBe(50);
});

test('one email cannot hold many unpaid tickets, from any connection', function () {
    foreach (['1.1.1.1', '2.2.2.2', '3.3.3.3'] as $address) {
        $this->withServerVariables(['REMOTE_ADDR' => $address])->post(route('orders.store'), attackForm(['quantity' => 10]));
    }

    expect((int) Order::query()->unpaidHold()->sum('quantity'))->toBe(20);
});

test('someone holding nothing can always place one order, however big it’s allowed to be', function () {
    config()->set('payments.unpaid_limits.tickets_per_buyer', 5);

    $this->post(route('orders.store'), attackForm(['quantity' => 10]))->assertRedirect();

    expect(Order::query()->sole()->quantity)->toBe(10);
});

test('paying frees up the limit on unpaid tickets', function () {
    config()->set('payments.unpaid_limits.tickets_per_buyer', 2);
    $this->post(route('orders.store'), attackForm(['quantity' => 2]));
    deliverBachsWebhook(chargeLanded(Payment::query()->sole()))->assertOk();

    $this->post(route('orders.store'), attackForm(['quantity' => 2]))->assertSessionDoesntHaveErrors();

    expect(Order::query()->count())->toBe(2);
});

test('an unpaid order cannot keep its hold for ever by asking to pay again', function () {
    $order = placeAttackOrder(10);

    foreach (range(1, 10) as $round) {
        $this->travel(50)->minutes();
        $this->post($order->signedUrl('orders.pay'), ['channel' => $this->channel->id]);
    }

    expect(Order::query()->holdingTickets()->whereKey($order->id)->exists())->toBeFalse()
        ->and($order->payments()->count())->toBe(5);
});

test('the buyer is told when an order has had too many tries', function () {
    $order = placeAttackOrder();
    config()->set('payments.unpaid_limits.attempts_per_order', 1);
    openAttackPayment($order);

    $this->post($order->signedUrl('orders.pay'), ['channel' => $this->channel->id])->assertRedirect($order->statusUrl());

    $this->get($order->statusUrl())->assertSeeText('too many tries');
});

test('a checkout that couldn’t open doesn’t use up one of the order’s tries', function () {
    $order = placeAttackOrder();
    config()->set('payments.unpaid_limits.attempts_per_order', 1);
    Sleep::fake();
    Http::swap(new HttpFactory);
    Http::fake(['*' => Http::response(['detail' => 'Down'], 503)]);

    expect(fn () => openAttackPayment($order))->toThrow(CheckoutUnavailable::class);

    Http::swap(new HttpFactory);
    fakeBachs();

    expect(openAttackPayment($order->fresh())->provider_checkout_id)->not->toBeNull();
});

test('switching sales off stops unpaid orders opening new checkouts', function () {
    $order = placeAttackOrder();
    $this->ticketType->update(['is_active' => false]);

    expect(fn () => openAttackPayment($order->fresh()))->toThrow(TicketsUnavailable::class);
    expect($order->payments()->count())->toBe(0);
});

/* ---------------------------------------------------------------- Stretching coupons and old prices */

test('unpaid orders from one connection cannot use up a limited coupon', function () {
    $coupon = Coupon::factory()->create(['discount_value' => 50, 'max_orders' => 3]);

    foreach (range(1, 3) as $try) {
        $this->post(route('orders.store'), attackForm(['email' => "fake{$try}@example.com", 'coupon' => $coupon->code]));
    }

    expect($coupon->fresh()->timesTaken())->toBe(1);

    $this->withServerVariables(['REMOTE_ADDR' => '5.5.5.5'])
        ->post(route('orders.store'), attackForm(['email' => 'real@example.com', 'coupon' => $coupon->code]))
        ->assertSessionDoesntHaveErrors();

    expect($coupon->fresh()->timesTaken())->toBe(2);
});

test('one email cannot take two unpaid uses of a limited coupon, from any connection', function () {
    $coupon = Coupon::factory()->create(['max_orders' => 3]);

    $this->withServerVariables(['REMOTE_ADDR' => '1.1.1.1'])->post(route('orders.store'), attackForm(['coupon' => $coupon->code]));
    $this->withServerVariables(['REMOTE_ADDR' => '2.2.2.2'])
        ->post(route('orders.store'), attackForm(['coupon' => $coupon->code]))
        ->assertSessionHasErrors(['coupon' => 'There’s already an unpaid order using this coupon from this email or connection. Pay for that one, or try again once its hold runs out.']);
});

test('a coupon with no limit can be used by the same person more than once', function () {
    $coupon = Coupon::factory()->create();

    $this->post(route('orders.store'), attackForm(['coupon' => $coupon->code]));
    $this->post(route('orders.store'), attackForm(['coupon' => $coupon->code]))->assertSessionDoesntHaveErrors();

    expect($coupon->fresh()->timesTaken())->toBe(2);
});

test('a disabled coupon comes off the next time its order asks to pay, and the buyer is told', function () {
    $coupon = Coupon::factory()->create(['discount_value' => 99]);
    $order = placeAttackOrder(10, $coupon->code);
    openAttackPayment($order);
    $coupon->forceFill(['disabled_at' => now()])->save();

    $this->post($order->signedUrl('orders.pay'), ['channel' => $this->channel->id])->assertRedirect($order->statusUrl());

    expect($order->fresh())->coupon_id->toBeNull()->discount_amount->toBe(0)->amount->toBe(10_000_000);
    $this->get($order->statusUrl())->assertSeeText('back to full price');
});

test('a coupon whose last use this order holds stays on the order when it asks to pay again', function () {
    $coupon = Coupon::factory()->create(['discount_value' => 50, 'max_orders' => 1]);
    $order = placeAttackOrder(1, $coupon->code);
    openAttackPayment($order);

    $second = openAttackPayment($order->fresh());

    expect($order->fresh()->coupon_id)->toBe($coupon->id)
        ->and($second->amount)->toBe(500_000);
});

test('an abandoned order comes back at today’s price, and the buyer is told', function () {
    $this->ticketType->update(['price' => 500_000]);
    $order = placeAttackOrder();
    $order->update(['status' => OrderStatus::Abandoned, 'abandoned_at' => now(), 'expires_at' => now()->subMinute()]);
    $this->ticketType->update(['price' => 1_000_000]);

    $this->post($order->signedUrl('orders.pay'), ['channel' => $this->channel->id])->assertRedirect($order->statusUrl());

    expect($order->fresh())->unit_price->toBe(1_000_000)->amount->toBe(1_000_000)->status->toBe(OrderStatus::Pending)
        ->and($order->payments()->count())->toBe(0);
    $this->get($order->statusUrl())->assertSeeText('ticket price changed');
});

test('an abandoned order’s coupon is worked out again on today’s price', function () {
    $coupon = Coupon::factory()->create(['discount_value' => 50]);
    $this->ticketType->update(['price' => 500_000]);
    $order = placeAttackOrder(1, $coupon->code);
    $order->update(['status' => OrderStatus::Abandoned, 'abandoned_at' => now(), 'expires_at' => now()->subMinute()]);
    $this->ticketType->update(['price' => 1_000_000]);

    expect(fn () => openAttackPayment($order->fresh()))->toThrow(CheckoutUnavailable::class);

    expect($order->fresh())->discount_amount->toBe(500_000)->amount->toBe(500_000)->coupon_id->toBe($coupon->id);
});

test('an order being paid for keeps its price', function () {
    $order = placeAttackOrder();
    $this->ticketType->update(['price' => 2_000_000]);

    expect(openAttackPayment($order->fresh())->amount)->toBe(1_000_000);
});

/* ---------------------------------------------------------------- Late money */

test('a late payment that oversells the event is honoured and flagged', function () {
    $this->ticketType->update(['capacity' => 1]);
    $late = placeAttackOrder();
    $latePayment = openAttackPayment($late);
    $this->travel(2)->hours();

    deliverBachsWebhook(chargeLanded(openAttackPayment(placeAttackOrder())))->assertOk();
    deliverBachsWebhook(chargeLanded($latePayment))->assertOk();

    expect($late->fresh())->status->toBe(OrderStatus::Paid)->review_note->toContain('oversold by 1');
});

test('a late payment that takes a coupon over its limit is honoured and flagged', function () {
    $coupon = Coupon::factory()->create(['discount_value' => 50, 'max_orders' => 1]);
    $late = placeAttackOrder(1, $coupon->code);
    $latePayment = openAttackPayment($late);
    $this->travel(2)->hours();

    deliverBachsWebhook(chargeLanded(openAttackPayment(placeAttackOrder(1, $coupon->code))))->assertOk();
    deliverBachsWebhook(chargeLanded($latePayment))->assertOk();

    expect($late->fresh())->status->toBe(OrderStatus::Paid)->review_note->toContain('over its limit of 1');
});

test('paying an earlier checkout at a price the order has since left behind is flagged', function () {
    $coupon = Coupon::factory()->create(['discount_value' => 50]);
    $order = placeAttackOrder(1, $coupon->code);
    $cheap = openAttackPayment($order);
    $coupon->forceFill(['disabled_at' => now()])->save();
    rescue(fn () => openAttackPayment($order->fresh()), report: false);

    deliverBachsWebhook(chargeLanded($cheap))->assertOk();

    expect($order->fresh())->status->toBe(OrderStatus::Paid)->review_note->toContain('on an earlier checkout');
});

test('asking to pay at the moment a late payment lands does not undo the paid order', function () {
    $order = placeAttackOrder();
    $payment = openAttackPayment($order);
    $order->update(['status' => OrderStatus::Abandoned, 'abandoned_at' => now(), 'expires_at' => now()->subMinute()]);
    $whatThisRequestRead = Order::query()->findOrFail($order->id);

    deliverBachsWebhook(chargeLanded($payment))->assertOk();

    expect(fn () => app(StartPayment::class)->handle($whatThisRequestRead, $this->channel))->toThrow(CheckoutUnavailable::class);
    expect($order->fresh()->status)->toBe(OrderStatus::Paid);
});

test('a declined card leaves the payment open, so a later success is still noticed if its webhook is lost', function () {
    $order = placeAttackOrder();
    $payment = openAttackPayment($order);

    deliverBachsWebhook(bachsEvent('collection.failed', ['checkout_id' => $payment->provider_checkout_id, 'status' => 'FAILED', 'reason' => 'Declined'], id: 'evt_failed'))->assertOk();

    expect($payment->fresh())->status->toBe(PaymentStatus::Open)->failure_message->toBe('Declined');

    fakePaidBachsCheckout($payment);
    $this->travel(10)->minutes();
    $this->artisan('payments:reconcile')->assertSuccessful();

    expect($order->fresh()->status)->toBe(OrderStatus::Paid);
});

/* ---------------------------------------------------------------- Rate limits */

test('waiting on the order page does not lock the buyer out of paying', function () {
    $order = placeAttackOrder();
    openAttackPayment($order)->update(['last_checked_at' => now()]);

    foreach (range(1, 15) as $poll) {
        $this->get($order->signedUrl('orders.status'))->assertOk();
    }

    expect($this->post($order->signedUrl('orders.pay'), ['channel' => $this->channel->id])->getStatusCode())->not->toBe(429);
});

test('filling in the form does not lock the buyer out of ordering', function () {
    foreach (range(1, 11) as $event) {
        $this->post(route('checkout-events.store'), ['step' => 'form_started']);
    }

    expect($this->post(route('orders.store'), attackForm())->getStatusCode())->not->toBe(429);
});

test('separate buyers sharing one mobile network address can all buy', function () {
    foreach (range(1, 20) as $buyer) {
        $response = $this->post(route('orders.store'), attackForm(['email' => "buyer{$buyer}@example.com"]));
    }

    expect($response->getStatusCode())->not->toBe(429)
        ->and(Order::query()->count())->toBe(20);
});

test('one address and one email each still have a limit, which a fake X-Forwarded-For doesn’t get around', function () {
    foreach (range(1, 30) as $try) {
        $this->withServerVariables(['HTTP_X_FORWARDED_FOR' => "1.2.3.{$try}"])->post(route('orders.store'), attackForm(['email' => "buyer{$try}@example.com"]));
    }

    $this->withServerVariables(['HTTP_X_FORWARDED_FOR' => '9.9.9.9'])->post(route('orders.store'), attackForm(['email' => 'next@example.com']))->assertTooManyRequests();

    foreach (range(1, 5) as $try) {
        $this->withServerVariables(['REMOTE_ADDR' => "2.2.2.{$try}"])->post(route('orders.store'), attackForm(['email' => 'same@example.com']));
    }

    $this->withServerVariables(['REMOTE_ADDR' => '2.2.2.9'])->post(route('orders.store'), attackForm(['email' => 'SAME@example.com']))->assertTooManyRequests();
});

test('refreshing the order page cannot hammer the provider while it is down', function () {
    Sleep::fake();
    $order = placeAttackOrder();
    openAttackPayment($order);
    Http::fake(['sandbox-api.bachs.io/v1/checkout-sessions/*' => Http::response(['message' => 'down'], 500)]);

    foreach (range(1, 20) as $refresh) {
        $this->get($order->statusUrl(['from' => 'checkout']));
    }

    expect(collect(Http::recorded())->filter(fn (array $pair): bool => $pair[0]->method() === 'GET')->count())->toBeLessThanOrEqual(3);
});

/* ---------------------------------------------------------------- Names used against other people */

test('a name cannot plant a link in the emails', function (string $name) {
    $order = Order::factory()->paid()->create(['buyer_name' => $name, 'quantity' => 2]);
    $ticket = Ticket::factory()->for($order)->create(['ticket_type_id' => $order->ticket_type_id, 'holder_name' => $name, 'holder_email' => 'victim@example.com']);
    Ticket::factory()->for($order)->create(['ticket_type_id' => $order->ticket_type_id, 'holder_name' => $name, 'holder_email' => 'other@example.com']);
    $order->load(['ticketType', 'tickets']);

    expect((new TicketMail($ticket))->render())->not->toContain('href="https://evil.example');
    expect((new TicketsMail($order))->render())->not->toContain('href="https://evil.example');
})->with([
    'a link' => ['[Your ticket has expired](https://evil.example/pay)'],
    'an image' => ['![x](https://evil.example/pixel.png)'],
    'a link after a blank line' => ["Ada\n\n[Renew here](https://evil.example/pay)"],
]);

test('names with links in them are refused at checkout', function (string $name) {
    $this->post(route('orders.store'), attackForm(['name' => $name]))->assertSessionHasErrors('name');

    expect(Order::query()->count())->toBe(0);
})->with([
    'a Markdown link' => ['[Renew](https://evil.example)'],
    'a bare address' => ['Renew at https://evil.example'],
    'www' => ['www.evil.example'],
    'a web address with no scheme' => ['Pay at evil.com now'],
    'a spreadsheet formula' => ['=HYPERLINK("https://evil.example","Click")'],
]);

test('ordinary names are accepted at checkout', function (string $name) {
    $this->post(route('orders.store'), attackForm(['name' => $name]))->assertSessionDoesntHaveErrors();

    expect(Order::query()->sole()->buyer_name)->toBe($name);
})->with([
    'an apostrophe' => ['Ada O’Brien'],
    'a hyphen' => ['Anne-Marie Bello'],
    'initials' => ['A.B. Bello'],
    'a title' => ['Dr. Ada Bello'],
    'accents' => ['Ọlámidé Ṣàngó'],
    'brackets for a note' => ['Ada Bello (Mrs)'],
]);

test('a name typed across lines is stored on one line', function () {
    $this->post(route('orders.store'), attackForm(['name' => "Ada\n\nBello"]));

    expect(Order::query()->sole()->buyer_name)->toBe('Ada Bello');
});

test('a value already in the database cannot run as a formula in the CSV download', function (string $column, int $cell) {
    Order::factory()->paid()->create([$column => '=HYPERLINK("https://evil.example","Click")']);
    $this->actingAs(User::factory()->superAdmin()->create());

    $download = Livewire::test(ListOrders::class)->callAction('export');
    $rows = array_map('str_getcsv', explode("\n", trim(base64_decode($download->effects['download']['content']))));

    expect($rows[1][$cell])->toStartWith("'=");
})->with([
    'the buyer’s name' => ['buyer_name', 7],
    'the buyer’s email' => ['buyer_email', 8],
]);

test('phone numbers in the CSV download stay as they are', function () {
    Order::factory()->paid()->create(['buyer_phone' => '+2348031234567']);
    $this->actingAs(User::factory()->superAdmin()->create());

    $download = Livewire::test(ListOrders::class)->callAction('export');
    $rows = array_map('str_getcsv', explode("\n", trim(base64_decode($download->effects['download']['content']))));

    expect($rows[1][9])->toBe('+2348031234567');
});

/* ---------------------------------------------------------------- Scripts placing orders in bulk */

function turnOnBotCheck(array $answer = ['success' => true], int $status = 200): void
{
    config()->set('services.turnstile.enabled', true);
    config()->set('services.turnstile.site_key', 'site-key');
    config()->set('services.turnstile.secret_key', 'secret-key');
    Http::fake(['challenges.cloudflare.com/*' => Http::response($answer, $status)]);
}

test('with no keys set there is no bot check, on the page or when ordering', function () {
    $this->get(route('disco-run'))->assertOk()->assertDontSee('cf-turnstile')->assertDontSee('challenges.cloudflare.com');

    $this->post(route('orders.store'), attackForm())->assertRedirect();

    expect(Order::query()->count())->toBe(1);
    Http::assertNotSent(fn (ClientRequest $request): bool => str_contains($request->url(), 'challenges.cloudflare.com'));
});

test('with its keys set but the switch off, there is no bot check, on the page or when ordering', function () {
    turnOnBotCheck();
    config()->set('services.turnstile.enabled', false);

    $this->get(route('disco-run'))->assertOk()->assertDontSee('cf-turnstile')->assertDontSee('challenges.cloudflare.com');

    $this->post(route('orders.store'), attackForm())->assertRedirect();

    expect(Order::query()->count())->toBe(1);
    Http::assertNotSent(fn (ClientRequest $request): bool => str_contains($request->url(), 'challenges.cloudflare.com'));
});

test('with its keys set, the ticket form carries the bot check, and its secret stays off the page', function () {
    turnOnBotCheck();

    $this->get(route('disco-run'))
        ->assertOk()
        ->assertSee('data-sitekey="site-key"', false)
        ->assertSee('https://challenges.cloudflare.com/turnstile/v0/api.js', false)
        ->assertDontSee('secret-key');
});

test('an order from something that didn’t pass the bot check is refused', function (array $form, array $answer) {
    turnOnBotCheck($answer);

    $this->post(route('orders.store'), attackForm($form))->assertSessionHasErrors('bot_check');

    expect(Order::query()->count())->toBe(0);
})->with([
    'no token' => [[], ['success' => true]],
    'an empty token' => [['cf-turnstile-response' => ''], ['success' => true]],
    'a token sent as a list' => [['cf-turnstile-response' => ['a']], ['success' => true]],
    'a token longer than any real one' => [['cf-turnstile-response' => str_repeat('a', 2049)], ['success' => true]],
    'a made-up token' => [['cf-turnstile-response' => 'made-up'], ['success' => false, 'error-codes' => ['invalid-input-response']]],
    'a token already used' => [['cf-turnstile-response' => 'used'], ['success' => false, 'error-codes' => ['timeout-or-duplicate']]],
    'a request Cloudflare calls bad' => [['cf-turnstile-response' => 'odd'], ['success' => false, 'error-codes' => ['bad-request']]],
    'an answer that makes no sense' => [['cf-turnstile-response' => 'odd'], []],
]);

test('an order that passes the bot check goes through, and Cloudflare is told who asked', function () {
    turnOnBotCheck();

    $this->post(route('orders.store'), attackForm(['cf-turnstile-response' => 'good-token']))->assertSessionDoesntHaveErrors();

    expect(Order::query()->count())->toBe(1);
    Http::assertSent(fn (ClientRequest $request): bool => $request->url() === 'https://challenges.cloudflare.com/turnstile/v0/siteverify'
        && $request['secret'] === 'secret-key'
        && $request['response'] === 'good-token'
        && $request['remoteip'] === '127.0.0.1');
});

test('fixing a typo doesn’t use up the bot check’s token', function () {
    turnOnBotCheck();

    $this->post(route('orders.store'), attackForm(['email' => 'ada@', 'cf-turnstile-response' => 'good-token']))->assertSessionHasErrors('email');

    Http::assertNotSent(fn (ClientRequest $request): bool => str_contains($request->url(), 'challenges.cloudflare.com'));
});

test('if Cloudflare can’t give an answer, or our key is wrong, the sale goes on and the problem is reported', function (Closure $answer) {
    Exceptions::fake();
    config()->set('services.turnstile.enabled', true);
    config()->set('services.turnstile.site_key', 'site-key');
    config()->set('services.turnstile.secret_key', 'secret-key');
    Http::fake(['challenges.cloudflare.com/*' => $answer()]);

    $this->post(route('orders.store'), attackForm(['cf-turnstile-response' => 'good-token']))->assertSessionDoesntHaveErrors();

    expect(Order::query()->count())->toBe(1);
    Exceptions::assertReportedCount(1);
})->with([
    'it can’t be reached' => [fn () => Http::failedConnection()],
    'it’s having problems' => [fn () => Http::response('Bad gateway', 502)],
    'it had an internal error' => [fn () => Http::response(['success' => false, 'error-codes' => ['internal-error']])],
    'our secret key is wrong' => [fn () => Http::response(['success' => false, 'error-codes' => ['invalid-input-secret']])],
]);

test('being refused by the bot check is noted in the buyer’s journey', function () {
    turnOnBotCheck();

    $this->post(route('orders.store'), attackForm());

    expect(CheckoutEvent::query()->where('step', CheckoutStep::FormInvalid)->sole()->properties)->toBe(['fields' => ['bot_check']]);
});

/* ---------------------------------------------------------------- Browser protections */

test('every page asks browsers to be careful with it', function () {
    $this->get('/')
        ->assertHeader('X-Content-Type-Options', 'nosniff')
        ->assertHeader('X-Frame-Options', 'SAMEORIGIN')
        ->assertHeader('Referrer-Policy', 'strict-origin-when-cross-origin')
        ->assertHeaderMissing('Strict-Transport-Security');
});

test('the live site tells browsers to always use https', function () {
    app()->detectEnvironment(fn (): string => 'production');

    $this->get('/')->assertHeader('Strict-Transport-Security', 'max-age=31536000');
});

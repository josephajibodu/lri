<?php

use App\Enums\OrderStatus;
use App\Enums\PaymentFailureReason;
use App\Enums\PaymentMethod;
use App\Enums\PaymentStatus;
use App\Models\Order;
use App\Models\PaymentChannel;
use App\Models\TicketType;
use App\Payments\PaymentManager;
use App\Payments\Providers\FakeProvider;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;

uses(RefreshDatabase::class);

beforeEach(function () {
    config()->set('payments.fake', true);

    $this->ticketType = TicketType::factory()->create(['slug' => 'disco-run-standard', 'price' => 1_000_000]);
    $this->transfer = PaymentChannel::factory()->method(PaymentMethod::BankTransfer)->featured()->create();
});

function orderForm(array $overrides = []): array
{
    return [
        'ticket_type' => 'disco-run-standard',
        'quantity' => 2,
        'name' => 'Ada Bello',
        'email' => 'Ada@Example.com ',
        'phone' => '0803 123 4567',
        'marketing_consent' => '1',
        'channel' => test()->transfer->id,
        ...$overrides,
    ];
}

function fakeProvider(): FakeProvider
{
    return app(PaymentManager::class)->driver('fake');
}

test('ordering holds the tickets and sends the buyer to a checkout for the chosen method', function () {
    $response = $this->post(route('orders.store'), orderForm());

    $order = Order::query()->sole();
    $payment = $order->payments()->sole();

    $response->assertRedirect($payment->checkout_url);

    expect($order)
        ->status->toBe(OrderStatus::Pending)
        ->quantity->toBe(2)
        ->amount->toBe(2_000_000)
        ->buyer_email->toBe('ada@example.com')
        ->buyer_phone->toBe('+2348031234567')
        ->marketing_consent->toBeTrue()
        ->and($order->expires_at->isFuture())->toBeTrue()
        ->and($payment)
        ->provider->toBe('fake')
        ->method->toBe(PaymentMethod::BankTransfer)
        ->status->toBe(PaymentStatus::Open)
        ->amount->toBe(2_000_000)
        ->and($payment->provider_checkout_id)->not->toBeNull();

    $request = fakeProvider()->requests()[0];

    expect($request->methods)->toBe([PaymentMethod::BankTransfer])
        ->and($request->reference)->toBe($payment->reference)
        ->and($request->amount->format())->toBe('₦20,000')
        ->and($request->successUrl)->toContain('/orders/'.$order->reference)
        ->and($request->customerPhone)->toBe('+2348031234567');
});

test('the page’s own checkout gets the checkout as JSON to open over the page', function () {
    $this->postJson(route('orders.store'), orderForm())
        ->assertOk()
        ->assertJsonStructure(['checkout_url', 'overlay', 'status_url', 'cancelled_url'])
        ->assertJsonPath('overlay', null);
});

test('a Bachs checkout is opened as a pop-up over the page', function () {
    config()->set('payments.fake', false);
    config()->set('payments.providers.bachs.secret_key', 'sk_sandbox_test');
    Http::fake(['sandbox-api.bachs.io/v1/checkout-sessions' => Http::response(['checkout_id' => 'chk_1', 'checkout_url' => 'https://checkout.bachs.io/c/chk_1', 'expires_at' => now()->addHour()->toIso8601String()], 201)]);

    $this->postJson(route('orders.store'), orderForm())
        ->assertOk()
        ->assertJsonPath('checkout_url', 'https://checkout.bachs.io/c/chk_1')
        ->assertJsonPath('overlay', 'bachs');
});

test('the form explains what needs fixing', function (array $overrides, string $field) {
    $this->from(route('disco-run'))
        ->post(route('orders.store'), orderForm($overrides))
        ->assertRedirect(route('disco-run').'#tickets')
        ->assertSessionHasErrors($field);

    expect(Order::query()->count())->toBe(0);
})->with([
    'no name' => [['name' => ''], 'name'],
    'a bad email' => [['email' => 'ada@'], 'email'],
    'a bad phone' => [['phone' => '12345'], 'phone'],
    'too many tickets' => [['quantity' => 11], 'quantity'],
    'no way to pay' => [['channel' => null], 'channel'],
]);

test('a switched-off way to pay is refused', function () {
    $card = PaymentChannel::factory()->method(PaymentMethod::Card)->disabled()->create();

    $this->postJson(route('orders.store'), orderForm(['channel' => $card->id]))
        ->assertUnprocessable()
        ->assertJsonValidationErrors('channel');
});

test('ordering more tickets than are left is refused', function () {
    $this->ticketType->update(['capacity' => 3]);
    Order::factory()->for($this->ticketType)->paid()->create(['quantity' => 2]);

    $this->postJson(route('orders.store'), orderForm(['quantity' => 2]))
        ->assertUnprocessable()
        ->assertJsonValidationErrors(['quantity' => 'Only 1 ticket is left.']);
});

test('tickets held by an unpaid order whose checkout closed go back on sale', function () {
    $this->ticketType->update(['capacity' => 2]);
    Order::factory()->for($this->ticketType)->create(['quantity' => 2, 'expires_at' => now()->subMinute()]);

    $this->postJson(route('orders.store'), orderForm(['quantity' => 2]))->assertOk();
});

test('when the payment page can’t be opened, the buyer lands on their order to try again', function () {
    config()->set('payments.fake', false);
    config()->set('payments.providers.bachs.secret_key', 'sk_sandbox_test');
    Http::fake(['*' => Http::response(['detail' => 'Down', 'error_code' => 'SERVICE_UNAVAILABLE'], 503)]);

    $response = $this->post(route('orders.store'), orderForm());

    $order = Order::query()->sole();
    $payment = $order->payments()->sole();

    $response->assertRedirect($order->statusUrl())
        ->assertSessionHas('checkout_error');

    expect($payment->status)->toBe(PaymentStatus::Failed)
        ->and($payment->failure_reason)->toBe(PaymentFailureReason::ProviderError);
});

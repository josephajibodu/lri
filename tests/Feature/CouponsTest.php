<?php

use App\Actions\Checkout\RefreshPayment;
use App\Enums\AdminPermission;
use App\Enums\CheckoutStep;
use App\Enums\CouponDiscountType;
use App\Enums\CouponStatus;
use App\Enums\OrderStatus;
use App\Enums\PaymentMethod;
use App\Enums\StaffRole;
use App\Filament\Resources\Coupons\Pages\ManageCoupons;
use App\Filament\Resources\Orders\Pages\ListOrders;
use App\Mail\TicketsMail;
use App\Models\Coupon;
use App\Models\Order;
use App\Models\PaymentChannel;
use App\Models\TicketType;
use App\Models\User;
use App\Support\Money;
use Filament\Actions\Testing\TestAction;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Spatie\Permission\Models\Role;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->ticketType = TicketType::factory()->create(['slug' => 'disco-run-standard', 'price' => 1_000_000]);
    $this->transfer = PaymentChannel::factory()->method(PaymentMethod::BankTransfer)->featured()->create();
});

function couponOrderForm(array $overrides = []): array
{
    return [
        'ticket_type' => 'disco-run-standard',
        'quantity' => 2,
        'name' => 'Ada Bello',
        'email' => 'ada@example.com',
        'channel' => test()->transfer->id,
        ...$overrides,
    ];
}

test('a coupon takes the right amount off and always leaves the smallest charge', function (array $terms, int $quantity, int $expectedDiscount) {
    $coupon = Coupon::factory()->create($terms);

    expect($coupon->discountFor(Money::of(1_000_000, 'NGN'), $quantity)->amount)->toBe($expectedDiscount);
})->with([
    'a percentage of the whole order' => [['discount_value' => 20], 2, 400_000],
    'a percentage only on the tickets it covers' => [['discount_value' => 50, 'max_tickets_per_order' => 1], 3, 500_000],
    'a fixed amount off the total' => [['discount_type' => 'fixed_amount', 'discount_value' => 250_000], 2, 250_000],
    'a fixed amount bigger than the order leaves ₦100' => [['discount_type' => 'fixed_amount', 'discount_value' => 5_000_000], 1, 990_000],
    'a big percentage leaves ₦100' => [['discount_value' => 100], 1, 990_000],
]);

test('placing an order with a coupon charges the discounted total and records the coupon', function () {
    fakeBachs();
    $coupon = Coupon::factory()->create(['code' => 'LRI20']);

    $this->post(route('orders.store'), couponOrderForm(['coupon' => ' lri20 ']));

    $order = Order::query()->sole();

    expect($order)
        ->coupon_id->toBe($coupon->id)
        ->discount_amount->toBe(400_000)
        ->amount->toBe(1_600_000)
        ->and($order->payments()->sole()->amount)->toBe(1_600_000);
});

test('an order without a coupon pays full price', function () {
    fakeBachs();

    $this->post(route('orders.store'), couponOrderForm());

    expect(Order::query()->sole())->coupon_id->toBeNull()->discount_amount->toBe(0)->amount->toBe(2_000_000);
});

test('a coupon that can’t be used is refused with a reason and no order is placed', function (Closure $makeCoupon, string $message) {
    $makeCoupon();
    $ordersBefore = Order::query()->count();

    $this->from(route('disco-run'))
        ->post(route('orders.store'), couponOrderForm(['coupon' => 'LRI20']))
        ->assertRedirect(route('disco-run').'#tickets')
        ->assertSessionHasErrors(['coupon' => $message]);

    expect(Order::query()->count())->toBe($ordersBefore);
})->with([
    'unknown' => [fn () => null, 'We can’t find that coupon. Check the spelling and try again.'],
    'disabled' => [fn () => Coupon::factory()->disabled()->create(['code' => 'LRI20']), 'This coupon has ended.'],
    'past its end' => [fn () => Coupon::factory()->create(['code' => 'LRI20', 'ends_at' => now()->subMinute()]), 'This coupon has ended.'],
    'not started' => [fn () => Coupon::factory()->create(['code' => 'LRI20', 'starts_at' => now()->addDay()]), 'This coupon isn’t active yet.'],
    'used up' => [function () {
        $coupon = Coupon::factory()->create(['code' => 'LRI20', 'max_orders' => 1]);
        Order::factory()->paid()->create(['coupon_id' => $coupon->id]);
    }, 'This coupon has been fully used.'],
    'for another ticket' => [function () {
        Coupon::factory()->create(['code' => 'LRI20'])->ticketTypes()->attach(TicketType::factory()->create());
    }, 'This coupon doesn’t work on this ticket.'],
]);

test('only paid orders and orders being paid for use up a coupon', function () {
    $coupon = Coupon::factory()->create();

    Order::factory()->paid()->create(['coupon_id' => $coupon->id]);
    Order::factory()->create(['coupon_id' => $coupon->id, 'expires_at' => now()->addHour()]);
    Order::factory()->create(['coupon_id' => $coupon->id, 'expires_at' => now()->subMinute()]);
    Order::factory()->abandoned()->create(['coupon_id' => $coupon->id]);
    Order::factory()->create(['coupon_id' => $coupon->id, 'status' => OrderStatus::Refunded]);

    expect($coupon->timesTaken())->toBe(2);
});

test('an abandoned order keeps a coupon that still works when the buyer comes back', function () {
    fakeBachs();
    $coupon = Coupon::factory()->create();
    $order = Order::factory()->abandoned()->create([
        'ticket_type_id' => $this->ticketType->id, 'quantity' => 1, 'coupon_id' => $coupon->id, 'discount_amount' => 200_000, 'amount' => 800_000,
    ]);

    $this->post($order->signedUrl('orders.pay'), ['channel' => $this->transfer->id]);

    expect($order->refresh()->amount)->toBe(800_000)->and($order->payments()->sole()->amount)->toBe(800_000);
});

test('an abandoned order goes back to full price when its coupon has ended, and the buyer is told before paying', function () {
    fakeBachs();
    $coupon = Coupon::factory()->disabled()->create(['code' => 'LRI20']);
    $order = Order::factory()->abandoned()->create([
        'ticket_type_id' => $this->ticketType->id, 'quantity' => 1, 'coupon_id' => $coupon->id, 'discount_amount' => 200_000, 'amount' => 800_000,
    ]);

    $this->postJson($order->signedUrl('orders.pay'), ['channel' => $this->transfer->id])
        ->assertConflict()
        ->assertSessionHas('checkout_error', 'Coupon LRI20 has ended, so your order is back to full price: ₦10,000. Choose how you’d like to pay to carry on.');

    expect($order->refresh())
        ->coupon_id->toBeNull()
        ->discount_amount->toBe(0)
        ->amount->toBe(1_000_000)
        ->and($order->payments()->count())->toBe(0);
});

test('checking a coupon says what it takes off at each quantity', function () {
    $this->ticketType->update(['max_per_order' => 2]);
    Coupon::factory()->create(['code' => 'LRI20', 'max_tickets_per_order' => 1]);

    $this->postJson(route('coupons.check'), ['ticket_type' => 'disco-run-standard', 'coupon' => 'lri20'])
        ->assertOk()
        ->assertExactJson([
            'code' => 'LRI20',
            'description' => '20% off on 1 ticket',
            'discounts' => ['1' => 200_000, '2' => 200_000],
        ]);
});

test('checking a coupon that can’t be used gives the reason', function () {
    $this->postJson(route('coupons.check'), ['ticket_type' => 'disco-run-standard', 'coupon' => 'NOPE'])
        ->assertUnprocessable()
        ->assertJsonPath('errors.coupon.0', 'We can’t find that coupon. Check the spelling and try again.');
});

test('a coupon’s terms can’t change, but it can be switched off', function () {
    $coupon = Coupon::factory()->create(['discount_value' => 20]);

    expect(fn () => $coupon->update(['discount_value' => 90]))->toThrow(LogicException::class);

    $coupon->refresh()->forceFill(['disabled_at' => now()])->save();

    expect($coupon->refresh())->discount_value->toBe(20)->disabled_at->not->toBeNull();
});

test('the ticket form offers a coupon box, folded away until someone needs it', function () {
    $this->get(route('disco-run'))
        ->assertOk()
        ->assertSeeText('Have a coupon?')
        ->assertSee('name="coupon"', false)
        ->assertSee(route('coupons.check'), false);
});

test('paying the discounted total counts as paid in full, and the receipt names the coupon', function () {
    fakeBachs();
    Coupon::factory()->create(['code' => 'LRI20']);

    $this->post(route('orders.store'), couponOrderForm(['coupon' => 'LRI20']));

    $order = Order::query()->sole();
    $this->get($order->statusUrl())->assertOk()->assertSeeText('Coupon LRI20')->assertSeeText('−₦4,000')->assertSeeText('₦16,000');

    fakePaidBachsCheckout($order->payments()->sole());
    app(RefreshPayment::class)->handle($order->payments()->sole());

    expect($order->refresh()->status)->toBe(OrderStatus::Paid);

    $this->get($order->receiptUrl())->assertOk()->assertSeeText('Coupon LRI20')->assertSeeText('−₦4,000')->assertSeeText('₦16,000');
});

test('an order without a coupon doesn’t mention coupons on its pages or email', function () {
    $order = Order::factory()->paid()->create(['ticket_type_id' => $this->ticketType->id, 'quantity' => 1, 'amount' => 1_000_000]);

    $this->get($order->receiptUrl())->assertOk()->assertDontSeeText('Coupon');
    (new TicketsMail($order))->assertDontSeeInHtml('Coupon')->assertSeeInHtml('1 × ₦10,000 = ₦10,000');
});

test('the tickets email spells out the coupon', function () {
    $coupon = Coupon::factory()->create(['code' => 'LRI20']);
    $order = Order::factory()->paid()->create([
        'ticket_type_id' => $this->ticketType->id, 'quantity' => 2, 'coupon_id' => $coupon->id, 'discount_amount' => 400_000, 'amount' => 1_600_000,
    ]);

    (new TicketsMail($order))
        ->assertSeeInHtml('Coupon LRI20')
        ->assertSeeInHtml('2 × ₦10,000 = ₦20,000')
        ->assertSeeInHtml('−₦4,000')
        ->assertSeeInHtml('₦16,000');
});

test('the journey records which coupon an order used', function () {
    fakeBachs();
    Coupon::factory()->create(['code' => 'LRI20']);

    $this->post(route('orders.store'), couponOrderForm(['coupon' => 'LRI20']));

    expect(Order::query()->sole()->checkoutEvents()->where('step', CheckoutStep::DetailsSubmitted)->sole()->properties['coupon'])->toBe('LRI20');
});

test('organisers can create a coupon with its limits, and amounts are stored in kobo', function () {
    $this->actingAs(User::factory()->superAdmin()->create());

    Livewire::test(ManageCoupons::class)
        ->callAction('create', data: [
            'code' => 'live-test',
            'note' => 'Live test purchase',
            'discount_type' => CouponDiscountType::FixedAmount->value,
            'discount_value' => '9900',
            'limit_dates' => true,
            'starts_at' => '2026-12-01 09:00:00',
            'ends_at' => '2026-12-02 09:00:00',
            'limit_orders' => true,
            'max_orders' => 1,
            'limit_ticket_types' => true,
            'ticketTypes' => [$this->ticketType->id],
        ])
        ->assertHasNoActionErrors();

    $coupon = Coupon::query()->sole();

    expect($coupon)
        ->code->toBe('LIVE-TEST')
        ->discount_type->toBe(CouponDiscountType::FixedAmount)
        ->discount_value->toBe(990_000)
        ->max_orders->toBe(1)
        ->max_tickets_per_order->toBeNull()
        ->and($coupon->starts_at->toDateTimeString())->toBe('2026-12-01 08:00:00')
        ->and($coupon->ticketTypes->pluck('id')->all())->toBe([$this->ticketType->id]);
});

test('a new coupon records who made it', function () {
    $organiser = User::factory()->superAdmin()->create();
    $this->actingAs($organiser);

    Livewire::test(ManageCoupons::class)
        ->callAction('create', ['code' => 'WHO-MADE-IT', 'discount_type' => CouponDiscountType::Percentage->value, 'discount_value' => 10])
        ->assertHasNoFormErrors();

    expect(Coupon::query()->withCode('WHO-MADE-IT')->sole()->creator->is($organiser))->toBeTrue();
});

test('a percentage coupon stores its percentage and how many tickets it covers', function () {
    $this->actingAs(User::factory()->superAdmin()->create());

    Livewire::test(ManageCoupons::class)
        ->callAction('create', data: ['code' => 'HALF', 'discount_type' => CouponDiscountType::Percentage->value, 'discount_value' => '50', 'limit_tickets' => true, 'max_tickets_per_order' => 2])
        ->assertHasNoActionErrors();

    expect(Coupon::query()->sole())->discount_value->toBe(50)->max_tickets_per_order->toBe(2);
});

test('the coupon form explains what needs fixing', function (array $overrides, string $field) {
    $this->actingAs(User::factory()->superAdmin()->create());
    Coupon::factory()->create(['code' => 'TAKEN']);

    Livewire::test(ManageCoupons::class)
        ->callAction('create', data: ['code' => 'NEW1', 'discount_type' => CouponDiscountType::Percentage->value, 'discount_value' => '20', ...$overrides])
        ->assertHasActionErrors([$field]);

    expect(Coupon::query()->count())->toBe(1);
})->with([
    'a code already used, in another case' => [['code' => 'taken'], 'code'],
    'a code with spaces' => [['code' => 'TWO WORDS'], 'code'],
    'no discount' => [['discount_value' => ''], 'discount_value'],
    'a percentage that would make it free' => [['discount_value' => '100'], 'discount_value'],
    'a fraction of a percent' => [['discount_value' => '12.5'], 'discount_value'],
    'an end before its start' => [['limit_dates' => true, 'starts_at' => '2026-12-02 09:00:00', 'ends_at' => '2026-12-01 09:00:00'], 'ends_at'],
    'an end already past' => [['limit_dates' => true, 'ends_at' => '2020-01-01 09:00:00'], 'ends_at'],
    'dates switched on with none chosen' => [['limit_dates' => true], 'ends_at'],
    'an order limit with no number' => [['limit_orders' => true], 'max_orders'],
    'no orders allowed' => [['limit_orders' => true, 'max_orders' => 0], 'max_orders'],
    'a ticket limit with no number' => [['limit_tickets' => true], 'max_tickets_per_order'],
    'certain tickets with none chosen' => [['limit_ticket_types' => true], 'ticketTypes'],
]);

test('a limit that’s switched off isn’t saved, even if it was filled in first', function () {
    $this->actingAs(User::factory()->superAdmin()->create());

    Livewire::test(ManageCoupons::class)
        ->callAction('create', data: [
            'code' => 'NOLIMITS',
            'discount_type' => CouponDiscountType::Percentage->value,
            'discount_value' => '20',
            'limit_dates' => false,
            'ends_at' => '2026-12-02 09:00:00',
            'limit_orders' => false,
            'max_orders' => 5,
            'limit_tickets' => false,
            'max_tickets_per_order' => 2,
            'limit_ticket_types' => false,
            'ticketTypes' => [$this->ticketType->id],
        ])
        ->assertHasNoActionErrors();

    $coupon = Coupon::query()->sole();

    expect($coupon)->ends_at->toBeNull()->max_orders->toBeNull()->max_tickets_per_order->toBeNull()
        ->and($coupon->ticketTypes)->toBeEmpty();
});

test('the form shows what a ticket will come to as the discount is typed', function (array $discount, string $heading, string $detail) {
    $this->actingAs(User::factory()->superAdmin()->create());

    Livewire::test(ManageCoupons::class)
        ->mountAction('create')
        ->assertMountedActionModalSee('Type an amount to see what buyers will pay')
        ->setActionData($discount)
        ->assertMountedActionModalSee($heading)
        ->assertMountedActionModalSee($detail)
        ->assertMountedActionModalDontSee('Type an amount to see what buyers will pay');
})->with([
    'a percentage' => [
        ['discount_type' => CouponDiscountType::Percentage->value, 'discount_value' => '20'],
        'One ₦10,000 ticket comes to ₦8,000',
        'The percentage comes off every ticket it covers.',
    ],
    'a naira amount' => [
        ['discount_type' => CouponDiscountType::FixedAmount->value, 'discount_value' => '2000'],
        'One ₦10,000 ticket comes to ₦8,000',
        'The amount comes off the order’s total, however many tickets they buy.',
    ],
    'more than the ticket costs' => [
        ['discount_type' => CouponDiscountType::FixedAmount->value, 'discount_value' => '50000'],
        'One ₦10,000 ticket comes to ₦100',
        'That’s the least a buyer can pay, because Bachs can’t charge less than ₦100.',
    ],
]);

test('switching between percentage and naira clears the amount, so 20% can’t quietly become ₦20', function () {
    $this->actingAs(User::factory()->superAdmin()->create());

    Livewire::test(ManageCoupons::class)
        ->mountAction('create')
        ->fillForm(['discount_value' => '20'])
        ->assertActionDataSet(['discount_value' => '20'])
        ->fillForm(['discount_type' => CouponDiscountType::FixedAmount->value])
        ->assertActionDataSet(['discount_value' => null]);
});

test('choosing just an end day means all of that day, and a time chosen afterwards is kept (Lagos is an hour ahead of what’s stored)', function () {
    $this->actingAs(User::factory()->superAdmin()->create());

    Livewire::test(ManageCoupons::class)
        ->mountAction('create')
        ->fillForm(['limit_dates' => true, 'ends_at' => '2026-12-01 00:00:00'])
        ->assertActionDataSet(['ends_at' => '2026-12-01 22:59'])
        ->fillForm(['ends_at' => '2026-12-01 00:00:00'])
        ->assertActionDataSet(['ends_at' => '2026-11-30 23:00']);
});

test('the coupon form can suggest a new code', function () {
    $this->actingAs(User::factory()->superAdmin()->create());

    Livewire::test(ManageCoupons::class)
        ->mountAction('create')
        ->fillForm(['code' => 'MINE'])
        ->callAction(TestAction::make('newCode')->schemaComponent('code'))
        ->assertActionDataSet(fn (array $data): bool => $data['code'] !== 'MINE' && strlen($data['code']) === 8);
});

test('coupons can’t be edited or deleted in the admin, only disabled and turned back on', function () {
    $this->actingAs(User::factory()->superAdmin()->create());
    $coupon = Coupon::factory()->create(['code' => 'LRI20']);

    Livewire::test(ManageCoupons::class)
        ->assertActionDoesNotExist(TestAction::make('edit')->table($coupon))
        ->assertActionDoesNotExist(TestAction::make('delete')->table($coupon))
        ->assertActionVisible(TestAction::make('view')->table($coupon))
        ->assertActionHidden(TestAction::make('turnBackOn')->table($coupon))
        ->callAction(TestAction::make('disable')->table($coupon));

    expect($coupon->refresh()->status())->toBe(CouponStatus::Disabled)
        ->and($coupon->whyUnusableOn($this->ticketType)?->getMessage())->toBe('This coupon has ended.');

    Livewire::test(ManageCoupons::class)
        ->assertActionHidden(TestAction::make('disable')->table($coupon))
        ->callAction(TestAction::make('turnBackOn')->table($coupon));

    expect($coupon->refresh()->status())->toBe(CouponStatus::Active);
});

test('the coupons list shows how much each coupon has been used', function () {
    $this->actingAs(User::factory()->superAdmin()->create());
    $coupon = Coupon::factory()->create(['code' => 'LRI20', 'max_orders' => 5]);
    Order::factory()->paid()->create(['coupon_id' => $coupon->id, 'discount_amount' => 400_000]);
    Order::factory()->create(['coupon_id' => $coupon->id, 'discount_amount' => 200_000, 'expires_at' => now()->addHour()]);

    $this->get('/admin/coupons')
        ->assertOk()
        ->assertSeeText('LRI20')
        ->assertSeeText('1 of 5')
        ->assertSeeText('1 paying now')
        ->assertSeeText('₦4,000');
});

test('the orders list can be narrowed to one coupon, and shows the code on each order', function () {
    $this->actingAs(User::factory()->superAdmin()->create());
    $coupon = Coupon::factory()->create(['code' => 'LRI20']);
    $withCoupon = Order::factory()->paid()->create(['coupon_id' => $coupon->id, 'discount_amount' => 400_000]);
    $without = Order::factory()->paid()->create();

    Livewire::test(ListOrders::class)
        ->filterTable('coupon_id', $coupon->id)
        ->assertCanSeeTableRecords([$withCoupon])
        ->assertCanNotSeeTableRecords([$without])
        ->assertSeeText('LRI20');

    $this->get('/admin/orders/'.$withCoupon->id)->assertOk()->assertSeeText('LRI20')->assertSeeText('20% off');
});

test('the orders download includes the coupon and discount', function () {
    $this->actingAs(User::factory()->superAdmin()->create());
    $coupon = Coupon::factory()->create(['code' => 'LRI20']);
    Order::factory()->paid()->create(['quantity' => 2, 'coupon_id' => $coupon->id, 'discount_amount' => 400_000, 'amount' => 1_600_000]);

    $response = Livewire::test(ListOrders::class)->callAction('export');
    $csv = base64_decode($response->effects['download']['content']);

    expect($csv)->toContain('Coupon,Discount,Total')->toContain('2,LRI20,4000.00,16000.00');
});

test('the Admin role can use the coupons section, and desk staff can’t', function () {
    expect(Role::findByName(StaffRole::Admin->value)->hasPermissionTo(AdminPermission::ManageCoupons->value))->toBeTrue()
        ->and(Role::findByName(StaffRole::HeadsetDesk->value)->hasPermissionTo(AdminPermission::ManageCoupons->value))->toBeFalse();
});

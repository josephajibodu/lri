<?php

use App\Enums\AdminPermission;
use App\Enums\CheckoutStep;
use App\Enums\OrderStatus;
use App\Filament\Pages\AbandonedOrders;
use App\Mail\AbandonedOrderMail;
use App\Models\Coupon;
use App\Models\Order;
use App\Models\TicketType;
use App\Models\User;
use Filament\Actions\Testing\TestAction;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\URL;
use Livewire\Features\SupportTesting\Testable;
use Livewire\Livewire;

uses(RefreshDatabase::class);

beforeEach(function () {
    Mail::fake();

    $this->ticketType = TicketType::factory()->create();
    $this->organiser = User::factory()->withPermissions(AdminPermission::SendReminders)->create();
});

/**
 * The abandoned orders page, as an organiser who can send reminders sees it.
 */
function abandonedOrdersPage(): Testable
{
    test()->actingAs(test()->organiser);

    return Livewire::test(AbandonedOrders::class);
}

function abandonedOrder(array $attributes = []): Order
{
    return Order::factory()->abandoned()->for(test()->ticketType)->create($attributes);
}

test('only people whose roles include it can open the abandoned orders page', function () {
    $this->get('/admin/abandoned-orders')->assertRedirect('/admin/login');

    $this->actingAs(User::factory()->withPermissions(AdminPermission::ManageOrders)->create())
        ->get('/admin/abandoned-orders')
        ->assertForbidden();

    $this->actingAs($this->organiser)->get('/admin/abandoned-orders')->assertOk()->assertSeeText('Abandoned orders');
});

test('sending everyone ready emails each waiting buyer once and skips those who bought or ordered again', function () {
    $waiting = abandonedOrder(['buyer_email' => 'ada@example.com']);
    $boughtSince = abandonedOrder(['buyer_email' => 'tunde@example.com']);
    Order::factory()->paid()->for($this->ticketType)->create(['buyer_email' => 'Tunde@Example.com']);
    $olderTry = abandonedOrder(['buyer_email' => 'kemi@example.com']);
    $latestTry = abandonedOrder(['buyer_email' => 'kemi@example.com']);
    $stillPaying = abandonedOrder(['buyer_email' => 'bisi@example.com']);
    Order::factory()->for($this->ticketType)->create(['buyer_email' => 'bisi@example.com']);

    abandonedOrdersPage()->callAction('remindEveryone')->assertNotified('2 reminders on their way');

    Mail::assertQueued(AbandonedOrderMail::class, 2);
    Mail::assertQueued(AbandonedOrderMail::class, fn (AbandonedOrderMail $mail): bool => $mail->hasTo('ada@example.com') && $mail->order->is($waiting));
    Mail::assertQueued(AbandonedOrderMail::class, fn (AbandonedOrderMail $mail): bool => $mail->hasTo('kemi@example.com') && $mail->order->is($latestTry));
    expect($waiting->fresh()->reminder_sent_at)->not->toBeNull()
        ->and($boughtSince->fresh()->reminder_sent_at)->toBeNull()
        ->and($olderTry->fresh()->reminder_sent_at)->toBeNull()
        ->and($stillPaying->fresh()->reminder_sent_at)->toBeNull()
        ->and($waiting->checkoutEvents()->where('step', CheckoutStep::ReminderSent)->exists())->toBeTrue();
    $this->assertDatabaseHas('activity_log', ['causer_id' => $this->organiser->id, 'subject_id' => $waiting->id, 'description' => "Emailed a reminder to finish order {$waiting->reference}, to ada@example.com"]);
});

test('a buyer never gets the reminder twice, even when it is pressed again for them', function () {
    $order = abandonedOrder();

    abandonedOrdersPage()->callAction(TestAction::make('remind')->table($order));
    abandonedOrdersPage()
        ->assertActionHidden(TestAction::make('remind')->table($order))
        ->callTableBulkAction('remindSelected', [$order])
        ->assertNotified('No reminders went');

    Mail::assertQueued(AbandonedOrderMail::class, 1);
});

test('reminders are paused while no tickets are on sale', function () {
    $this->ticketType->update(['is_active' => false]);
    $order = abandonedOrder();

    abandonedOrdersPage()
        ->assertActionDisabled('remindEveryone')
        ->assertActionHidden(TestAction::make('remind')->table($order));

    Mail::assertNothingQueued();
});

test('the reminder names the tickets at today’s price and its button picks up the order', function () {
    $coupon = Coupon::factory()->create(['code' => 'LRILOVE']);
    $order = abandonedOrder(['buyer_name' => 'Ada Bello', 'quantity' => 2, 'coupon_id' => $coupon->id]);
    $this->ticketType->update(['price' => 1_500_000]);

    $mail = new AbandonedOrderMail($order->load(['ticketType', 'coupon']));

    $mail->assertSeeInText('Still coming, Ada?');
    $mail->assertSeeInText('2 × ₦15,000');
    $mail->assertSeeInText('LRILOVE, filled in for you');
    $mail->assertSeeInHtml(e(route('orders.pick-up', $order->reference)), false);
});

test('the reminder’s link fills in the ticket form with what the buyer typed and tags the visit', function () {
    $coupon = Coupon::factory()->create(['code' => 'LRILOVE']);
    $order = abandonedOrder(['buyer_name' => 'Ada Bello', 'buyer_email' => 'ada@example.com', 'buyer_phone' => '+2348031234567', 'quantity' => 3, 'coupon_id' => $coupon->id]);

    $this->get(URL::temporarySignedRoute('orders.pick-up', now()->addDay(), ['order' => $order->reference]))
        ->assertRedirect(route('disco-run', ['utm_source' => 'email', 'utm_medium' => 'reminder', 'utm_campaign' => 'abandoned-order']).'#tickets')
        ->assertSessionHasInput(['quantity' => 3, 'name' => 'Ada Bello', 'email' => 'ada@example.com', 'phone' => '+2348031234567', 'coupon' => 'LRILOVE']);

    expect($order->checkoutEvents()->where('step', CheckoutStep::ReminderOpened)->exists())->toBeTrue();
});

test('the reminder’s link only works signed and before it runs out, and sends a buyer who paid to their receipt', function () {
    $order = abandonedOrder();

    $this->get(route('orders.pick-up', $order->reference))->assertForbidden();
    $this->get(URL::temporarySignedRoute('orders.pick-up', now()->subMinute(), ['order' => $order->reference]))->assertForbidden();

    $order->update(['status' => OrderStatus::Paid]);

    $this->get(URL::temporarySignedRoute('orders.pick-up', now()->addDay(), ['order' => $order->reference]))
        ->assertRedirect($order->receiptUrl());
});

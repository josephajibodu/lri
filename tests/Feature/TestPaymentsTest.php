<?php

use App\Enums\OrderStatus;
use App\Enums\PaymentStatus;
use App\Filament\Pages\HeadsetDesk;
use App\Filament\Resources\Orders\Pages\ListOrders;
use App\Filament\Resources\Orders\Pages\ViewOrder;
use App\Models\Coupon;
use App\Models\Order;
use App\Models\Payment;
use App\Models\PaymentChannel;
use App\Models\Ticket;
use App\Models\TicketType;
use App\Models\User;
use Filament\Actions\Testing\TestAction;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Livewire\Livewire;

uses(RefreshDatabase::class);

test('marking a paid order as a test payment frees its tickets and coupon use but keeps the record', function () {
    Mail::fake();
    $ticketType = TicketType::factory()->create(['capacity' => 100]);
    $coupon = Coupon::factory()->create();
    $order = Order::factory()->paid()->for($ticketType)->for($coupon)->create(['quantity' => 2]);
    $ticket = Ticket::factory()->for($order)->create(['ticket_type_id' => $ticketType->id]);
    $payment = Payment::factory()->for($order)->succeeded()->create();
    $organiser = User::factory()->superAdmin()->create();
    $this->actingAs($organiser);

    Livewire::test(ViewOrder::class, ['record' => $order->id])->callAction('markAsTestPayment');

    expect($order->fresh())
        ->status->toBe(OrderStatus::TestPayment)
        ->paid_at->not->toBeNull()
        ->marked_test_at->not->toBeNull()
        ->marked_test_by->toBe($organiser->id);
    expect($payment->fresh()->status)->toBe(PaymentStatus::Succeeded);
    expect($ticketType->ticketsLeft())->toBe(100);
    expect($coupon->timesTaken())->toBe(0);
    $this->assertModelExists($ticket);
    Mail::assertNothingOutgoing();
});

test('an order can only be marked as a test payment while it is paid', function (OrderStatus $status) {
    $order = Order::factory()->create(['status' => $status]);
    $this->actingAs(User::factory()->superAdmin()->create());

    Livewire::test(ViewOrder::class, ['record' => $order->id])->assertActionHidden('markAsTestPayment');
})->with([
    'waiting for payment' => OrderStatus::Pending,
    'abandoned' => OrderStatus::Abandoned,
    'refunded' => OrderStatus::Refunded,
    'disputed' => OrderStatus::Disputed,
    'already a test payment' => OrderStatus::TestPayment,
]);

test('an order with a collected headset cannot be marked as a test payment', function () {
    $order = Order::factory()->paid()->create();
    Ticket::factory()->for($order)->create(['ticket_type_id' => $order->ticket_type_id, 'checked_in_at' => now()]);
    $this->actingAs(User::factory()->superAdmin()->create());

    Livewire::test(ViewOrder::class, ['record' => $order->id])->assertActionHidden('markAsTestPayment');
});

test('organisers can see a test payment, who marked it, and find it with the status filter', function () {
    $organiser = User::factory()->superAdmin()->create(['name' => 'Kemi']);
    $testPayment = Order::factory()->testPayment()->create(['marked_test_by' => $organiser->id]);
    $realOrder = Order::factory()->paid()->create();
    $this->actingAs($organiser);

    Livewire::test(ViewOrder::class, ['record' => $testPayment->id])
        ->assertSeeText('Test payment')
        ->assertSeeText('Marked as a test payment')
        ->assertSeeText('By Kemi')
        ->assertActionHidden('resendTickets');

    Livewire::test(ListOrders::class)
        ->filterTable('status', [OrderStatus::TestPayment->value])
        ->assertCanSeeTableRecords([$testPayment])
        ->assertCanNotSeeTableRecords([$realOrder]);
});

test('a test payment’s ticket link stops working and its buyer is told why', function () {
    $order = Order::factory()->testPayment()->create(['quantity' => 1]);
    $ticket = Ticket::factory()->named()->for($order)->create(['ticket_type_id' => $order->ticket_type_id]);

    $this->get($ticket->claimUrl())->assertNotFound();
    $this->get($order->receiptUrl())->assertRedirect($order->statusUrl());
    $this->get($order->statusUrl())
        ->assertOk()
        ->assertSeeText('This was a test payment.')
        ->assertSeeText('its ticket doesn’t work for the event')
        ->assertDontSee('data-checkout', false);
});

test('the headset desk shows a test payment and offers no headset for it', function () {
    config()->set('lri.headset_desk.open', true);
    $order = Order::factory()->testPayment()->create();
    $ticket = Ticket::factory()->named()->for($order)->create(['ticket_type_id' => $order->ticket_type_id]);
    $this->actingAs(User::factory()->deskStaff()->create());

    Livewire::test(HeadsetDesk::class)
        ->assertSeeText('Test payment')
        ->assertActionHidden(TestAction::make('giveHeadset')->table($ticket))
        ->assertActionHidden(TestAction::make('saveAndGiveHeadset')->table($ticket));
});

test('a test payment cannot be paid for again', function () {
    $order = Order::factory()->testPayment()->create();
    $channel = PaymentChannel::factory()->create();

    $this->post($order->signedUrl('orders.pay'), ['channel' => $channel->id])
        ->assertRedirect($order->statusUrl())
        ->assertSessionHas('checkout_error');

    expect($order->payments()->count())->toBe(0);
    expect($order->fresh()->status)->toBe(OrderStatus::TestPayment);
});

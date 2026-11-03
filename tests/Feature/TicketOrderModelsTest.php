<?php

use App\Enums\OrderStatus;
use App\Enums\PaymentMethod;
use App\Enums\PaymentStatus;
use App\Models\Order;
use App\Models\Payment;
use App\Models\PaymentChannel;
use App\Models\Ticket;
use App\Models\TicketType;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

test('a new order gets a short reference and starts out pending', function () {
    $order = Order::factory()->create(['reference' => null]);

    expect($order->reference)->toMatch('/^LRI-[2-9A-HJ-NP-Z]{6}$/')
        ->and($order->status)->toBe(OrderStatus::Pending)
        ->and($order->total()->format())->toBe('₦'.number_format($order->amount / 100));
});

test('each payment attempt gets its own reference built from the order', function () {
    $order = Order::factory()->create();

    $first = Payment::factory()->for($order)->create(['reference' => null]);
    $second = Payment::factory()->for($order)->create(['reference' => null]);

    expect($first->reference)->toStartWith($order->reference.'-')
        ->and($second->reference)->toStartWith($order->reference.'-')
        ->and($first->reference)->not->toBe($second->reference)
        ->and($first->status)->toBe(PaymentStatus::Open)
        ->and($order->latestPayment->is($second))->toBeTrue();
});

test('a new ticket gets a code to show at the gate', function () {
    $ticket = Ticket::factory()->create(['code' => null]);

    expect($ticket->code)->toMatch('/^[2-9A-HJ-NP-Z]{4}-[2-9A-HJ-NP-Z]{4}$/');
});

test('only switched-on ticket types inside their sales window are on sale', function () {
    $onSale = TicketType::factory()->create();
    TicketType::factory()->inactive()->create();
    TicketType::factory()->create(['sales_start_at' => now()->addDay()]);
    TicketType::factory()->create(['sales_end_at' => now()->subMinute()]);
    $windowOpen = TicketType::factory()->create(['sales_start_at' => now()->subDay(), 'sales_end_at' => now()->addDay()]);

    expect(TicketType::onSale()->pluck('id')->all())->toEqualCanonicalizing([$onSale->id, $windowOpen->id]);
});

test('checkout offers the switched-on channels for the currency, featured first', function () {
    $card = PaymentChannel::factory()->method(PaymentMethod::Card)->create(['sort_order' => 1]);
    $transfer = PaymentChannel::factory()->method(PaymentMethod::BankTransfer)->featured()->create(['sort_order' => 2]);
    PaymentChannel::factory()->method(PaymentMethod::Ussd)->disabled()->create();
    PaymentChannel::factory()->method(PaymentMethod::Card)->create(['currency' => 'USD']);

    $offered = PaymentChannel::offeredIn('ngn')->get();

    expect($offered->pluck('id')->all())->toBe([$transfer->id, $card->id])
        ->and($offered->first()->display_label)->toBe('Bank transfer');
});

test('an admin label replaces the method name at checkout', function () {
    $channel = PaymentChannel::factory()->method(PaymentMethod::BankTransfer)->create(['label' => 'Pay by transfer']);

    expect($channel->display_label)->toBe('Pay by transfer');
});

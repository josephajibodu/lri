<?php

use App\Enums\EmailToolSendStatus;
use App\Enums\PaymentMethod;
use App\Filament\Pages\Dashboard;
use App\Filament\Resources\EmailToolSends\Pages\ManageEmailToolSends;
use App\Filament\Resources\Orders\Pages\ListOrders;
use App\Filament\Resources\Orders\Pages\ViewOrder;
use App\Filament\Resources\Orders\RelationManagers\CheckoutEventsRelationManager;
use App\Filament\Resources\PaymentChannels\Pages\ManagePaymentChannels;
use App\Filament\Resources\TicketTypes\Pages\CreateTicketType;
use App\Filament\Resources\WebhookCalls\Pages\ManageWebhookCalls;
use App\Filament\Widgets\CheckoutFunnelTable;
use App\Filament\Widgets\PaymentMethodsTable;
use App\Filament\Widgets\SalesBySourceTable;
use App\Filament\Widgets\SalesChart;
use App\Filament\Widgets\SalesOverview;
use App\Jobs\SendToEmailTool;
use App\Mail\TicketsMail;
use App\Models\CheckoutEvent;
use App\Models\EmailToolSend;
use App\Models\Order;
use App\Models\Payment;
use App\Models\PaymentChannel;
use App\Models\Ticket;
use App\Models\TicketType;
use App\Models\User;
use App\Models\WebhookCall;
use Filament\Actions\Testing\TestAction;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Queue;
use Livewire\Livewire;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->admin = User::factory()->create();
});

test('the admin and the logs ask guests to sign in', function () {
    $this->get('/admin')->assertRedirect('/admin/login');
    $this->get('/admin/orders')->assertRedirect('/admin/login');
    $this->get('/log-viewer')->assertRedirect('/admin/login');
});

test('an organiser sees the dashboard and the logs', function () {
    $this->actingAs($this->admin)->get('/admin')->assertOk()->assertSeeText('LRI Admin');
    $this->actingAs($this->admin)->get('/log-viewer')->assertOk();
});

test('every dashboard widget renders with data and without', function (string $widget) {
    $this->actingAs($this->admin);

    Livewire::test($widget)->assertOk();

    Payment::factory()->succeeded()->create(['paid_at' => now()]);
    Order::factory()->paid()->create(['attribution' => ['source' => 'instagram']]);
    CheckoutEvent::factory()->create();

    Livewire::test($widget, ['pageFilters' => ['range' => '7']])->assertOk();
    Livewire::test($widget, ['pageFilters' => ['range' => 'all']])->assertOk();
})->with([SalesOverview::class, SalesChart::class, CheckoutFunnelTable::class, PaymentMethodsTable::class, SalesBySourceTable::class]);

test('the dashboard period filter can be changed', function () {
    $this->actingAs($this->admin);

    Livewire::test(Dashboard::class)->set('filters.range', 'custom')->assertOk();
});

test('orders can be found by buyer or reference', function () {
    $this->actingAs($this->admin);
    $ada = Order::factory()->create(['buyer_name' => 'Ada Bello', 'buyer_email' => 'ada@example.com']);
    $tunde = Order::factory()->create(['buyer_name' => 'Tunde Ojo', 'buyer_email' => 'tunde@example.com']);

    Livewire::test(ListOrders::class)
        ->assertCanSeeTableRecords([$ada, $tunde])
        ->searchTable('ada@')
        ->assertCanSeeTableRecords([$ada])
        ->assertCanNotSeeTableRecords([$tunde])
        ->searchTable($tunde->reference)
        ->assertCanSeeTableRecords([$tunde]);
});

test('orders can be filtered by the method tried', function () {
    $this->actingAs($this->admin);
    $card = Payment::factory()->method(PaymentMethod::Card)->create()->order;
    $transfer = Payment::factory()->method(PaymentMethod::BankTransfer)->create()->order;

    Livewire::test(ListOrders::class)
        ->filterTable('method', PaymentMethod::Card->value)
        ->assertCanSeeTableRecords([$card])
        ->assertCanNotSeeTableRecords([$transfer]);
});

test('the orders shown can be downloaded as a spreadsheet', function () {
    $this->actingAs($this->admin);
    Order::factory()->create(['reference' => 'LRI-AAAAAA', 'buyer_email' => 'ada@example.com']);

    Livewire::test(ListOrders::class)->callAction('export')->assertFileDownloaded('lri-orders-'.now()->format('Y-m-d').'.csv');
});

test('an order shows its payments and the buyer’s journey', function () {
    $this->actingAs($this->admin);
    $order = Order::factory()->create();
    Payment::factory()->for($order)->failed()->create();
    $event = CheckoutEvent::factory()->create(['order_id' => $order->id]);

    Livewire::test(ViewOrder::class, ['record' => $order->getRouteKey()])->assertOk()->assertSee($order->reference);
    Livewire::test(CheckoutEventsRelationManager::class, ['ownerRecord' => $order, 'pageClass' => ViewOrder::class])
        ->assertOk()
        ->assertCanSeeTableRecords([$event]);
});

test('an organiser can check an open payment with the provider', function () {
    $this->actingAs($this->admin);
    fakeBachs();
    $ticketType = TicketType::factory()->create();
    $channel = PaymentChannel::factory()->method(PaymentMethod::BankTransfer)->create();
    $this->post(route('orders.store'), ['ticket_type' => $ticketType->slug, 'quantity' => 1, 'name' => 'Ada', 'email' => 'ada@example.com', 'channel' => $channel->id]);
    $order = Order::query()->sole();
    fakePaidBachsCheckout($order->latestPayment);

    Livewire::test(ViewOrder::class, ['record' => $order->getRouteKey()])
        ->callAction('checkWithProvider')
        ->assertNotified();

    expect($order->fresh()->tickets()->count())->toBe(1);
});

test('an organiser can email a paid order’s tickets again', function () {
    Mail::fake();
    $this->actingAs($this->admin);
    $order = Order::factory()->paid()->create();
    Ticket::factory()->for($order)->create(['ticket_type_id' => $order->ticket_type_id]);

    Livewire::test(ViewOrder::class, ['record' => $order->getRouteKey()])
        ->assertActionVisible('resendTickets')
        ->callAction('resendTickets');

    Mail::assertSent(TicketsMail::class, fn (TicketsMail $mail): bool => $mail->hasTo($order->buyer_email));
    expect($order->fresh()->tickets_emailed_at)->not->toBeNull();
});

test('a ticket type’s price is entered in naira and stored in kobo', function () {
    $this->actingAs($this->admin);

    Livewire::test(CreateTicketType::class)
        ->fillForm([
            'name' => 'Early bird',
            'slug' => 'disco-run-early-bird',
            'event' => 'disco-run',
            'price' => '7500',
            'currency' => 'NGN',
            'max_per_order' => 4,
            'is_active' => true,
        ])
        ->call('create')
        ->assertHasNoFormErrors();

    expect(TicketType::query()->where('slug', 'disco-run-early-bird')->sole())
        ->price->toBe(750_000)
        ->max_per_order->toBe(4);
});

test('a way to pay can be featured from the list', function () {
    $this->actingAs($this->admin);
    $card = PaymentChannel::factory()->method(PaymentMethod::Card)->create();

    Livewire::test(ManagePaymentChannels::class)
        ->call('updateTableColumnState', 'is_featured', (string) $card->getKey(), true);

    expect($card->fresh()->is_featured)->toBeTrue();
});

test('a webhook shows what the provider sent', function () {
    $this->actingAs($this->admin);
    $call = WebhookCall::factory()->create(['type' => 'customer.created', 'payload' => ['id' => 'cus_123', 'type' => 'customer.created']]);

    Livewire::test(ManageWebhookCalls::class)
        ->mountAction(TestAction::make('view')->table($call))
        ->assertMountedActionModalSee('cus_123');
});

test('a failed webhook can be handled again from the admin', function () {
    $this->actingAs($this->admin);
    $call = WebhookCall::factory()->create(['type' => 'customer.created', 'payload' => ['id' => 'x', 'type' => 'customer.created'], 'failed_at' => now(), 'error' => 'Boom']);

    Livewire::test(ManageWebhookCalls::class)
        ->callAction(TestAction::make('replay')->table($call))
        ->assertNotified();

    expect($call->fresh())->processed_at->not->toBeNull()->failed_at->toBeNull();
});

test('organisers can see what went to the email tool and why anything failed', function () {
    $this->actingAs($this->admin);
    $sent = EmailToolSend::factory()->sent()->create();
    $failed = EmailToolSend::factory()->failed('Mailchimp returned 400: Your merge fields were invalid.')->create();

    Livewire::test(ManageEmailToolSends::class)
        ->assertOk()
        ->assertCanSeeTableRecords([$sent, $failed])
        ->assertSee($failed->order->reference)
        ->assertSee('Your merge fields were invalid.')
        ->filterTable('status', EmailToolSendStatus::Failed->value)
        ->assertCanSeeTableRecords([$failed])
        ->assertCanNotSeeTableRecords([$sent]);
});

test('a failed send can be sent again from the admin, but one that went out can’t', function () {
    Queue::fake([SendToEmailTool::class]);
    $this->actingAs($this->admin);
    $sent = EmailToolSend::factory()->sent()->create();
    $failed = EmailToolSend::factory()->failed()->create();

    Livewire::test(ManageEmailToolSends::class)
        ->assertActionHidden(TestAction::make('sendAgain')->table($sent))
        ->callAction(TestAction::make('sendAgain')->table($failed))
        ->assertNotified();

    expect($failed->fresh())
        ->status->toBe(EmailToolSendStatus::Waiting)
        ->failed_at->toBeNull()
        ->error->toBeNull();
    Queue::assertPushed(SendToEmailTool::class, fn (SendToEmailTool $job): bool => $job->send->is($failed));
});

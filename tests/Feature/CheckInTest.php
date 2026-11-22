<?php

use App\Enums\AdminPermission;
use App\Enums\OrderStatus;
use App\Filament\Pages\Auth\Login;
use App\Filament\Pages\Dashboard;
use App\Filament\Pages\HeadsetDesk;
use App\Filament\Resources\Orders\Pages\ListOrders;
use App\Models\Order;
use App\Models\Ticket;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Testing\TestResponse;
use Livewire\Livewire;

uses(RefreshDatabase::class);

beforeEach(function () {
    config()->set('lri.headset_desk.open', true);
    config()->set('lri.headset_desk.check_in_open', true);
});

/**
 * A ticket on an order in the given state, with someone's name on it.
 */
function gateTicket(OrderStatus $status = OrderStatus::Paid, array $attributes = []): Ticket
{
    $order = Order::factory()->paid()->create(['status' => $status]);

    return Ticket::factory()->for($order)->create([
        'ticket_type_id' => $order->ticket_type_id,
        'holder_name' => 'Ada Bello',
        'holder_email' => 'ada@example.com',
        ...$attributes,
    ]);
}

/**
 * What the scanner sends after reading a QR code or having a code typed in.
 */
function scanAtGate(string $code, ?User $staff = null): TestResponse
{
    if ($staff) {
        test()->actingAs($staff);
    }

    return test()->postJson(route('check-in.scans'), ['code' => $code]);
}

test('anyone who can’t use the headset desk is sent to the home page from the scanner', function (?User $person) {
    if ($person) {
        $this->actingAs($person);
    }

    $this->get('/check-in')->assertRedirect(route('home'));
})->with([
    'a guest' => fn (): ?User => null,
    'an admin without the desk' => fn (): User => User::factory()->withPermissions(AdminPermission::ManageOrders, AdminPermission::ViewDashboard)->create(),
    'desk staff while the desk is hidden' => function (): User {
        config()->set('lri.headset_desk.open', false);

        return User::factory()->deskStaff()->create();
    },
]);

test('desk staff can open the scanner, which shows how many paid tickets have got someone in', function () {
    gateTicket(attributes: ['admitted_at' => now()]);
    gateTicket();
    gateTicket(OrderStatus::Abandoned);

    $this->actingAs(User::factory()->deskStaff()->create())
        ->get('/check-in')
        ->assertOk()
        ->assertSeeText('Start scanning')
        ->assertSeeText('1 of 2 let in')
        ->assertDontSeeText('Practice mode');
});

test('super admins can open the scanner while the desk is hidden', function () {
    config()->set('lri.headset_desk.open', false);

    $this->actingAs(User::factory()->superAdmin()->create())->get('/check-in')->assertOk();
});

test('a paid ticket gets one person in, and scanning it again is turned away with when and by whom', function () {
    $this->travelTo('2026-12-11 18:42:00');
    $staff = User::factory()->deskStaff()->create(['name' => 'Bisi Gate']);
    $ticket = gateTicket();

    scanAtGate($ticket->deskUrl(), $staff)
        ->assertOk()
        ->assertJson([
            'result' => 'let_in',
            'valid' => true,
            'heading' => 'Valid',
            'name' => 'Ada Bello',
            'code' => $ticket->code,
            'detail' => 'Let them in.',
            'counts' => ['let_in' => 1, 'tickets' => 1],
        ]);

    expect($ticket->fresh())
        ->admitted_at->toDateTimeString()->toBe('2026-12-11 18:42:00')
        ->admitted_by->toBe($staff->id);

    $this->travel(5)->minutes();

    scanAtGate($ticket->code, User::factory()->deskStaff()->create())
        ->assertOk()
        ->assertJson([
            'result' => 'already_let_in',
            'valid' => false,
            'heading' => 'Already used',
            'name' => 'Ada Bello',
            'detail' => 'Someone got in on this ticket at 11 Dec, 7:42 PM, scanned by Bisi Gate. Don’t let anyone else in on it.',
        ]);

    expect($ticket->fresh())->admitted_by->toBe($staff->id);
});

test('a ticket is found from its QR code’s link or its code typed in any way', function (string $scanned) {
    gateTicket(attributes: ['code' => '7K3Q-9PXM']);

    scanAtGate($scanned, User::factory()->deskStaff()->create())->assertJson(['result' => 'let_in']);
})->with([
    'the QR code' => fn (): string => (new Ticket(['code' => '7K3Q-9PXM']))->deskUrl(),
    'with its dash' => '7K3Q-9PXM',
    'without its dash, in small letters' => '7k3q9pxm',
    'with spaces around' => ' 7K3Q 9PXM ',
]);

test('a ticket that isn’t paid for gets nobody in and isn’t marked as used', function (OrderStatus $status, string $result, string $heading) {
    $ticket = gateTicket($status);

    scanAtGate($ticket->code, User::factory()->deskStaff()->create())
        ->assertOk()
        ->assertJson(['result' => $result, 'valid' => false, 'heading' => $heading]);

    expect($ticket->fresh()->admitted_at)->toBeNull();
})->with([
    'waiting for payment' => [OrderStatus::Pending, 'not_paid', 'Not paid'],
    'abandoned' => [OrderStatus::Abandoned, 'not_paid', 'Not paid'],
    'refunded' => [OrderStatus::Refunded, 'refunded', 'Refunded'],
    'payment disputed' => [OrderStatus::Disputed, 'disputed', 'Payment disputed'],
    'test payment' => [OrderStatus::TestPayment, 'test_payment', 'Test ticket'],
]);

test('a code no ticket has, such as a reissued ticket’s old one, is turned away', function () {
    $ticket = gateTicket(attributes: ['code' => '7K3Q-9PXM']);
    $ticket->update(['code' => 'ABCD-EFGH']);

    scanAtGate('7K3Q-9PXM', User::factory()->deskStaff()->create())
        ->assertOk()
        ->assertJson(['result' => 'not_found', 'valid' => false, 'heading' => 'Not a ticket', 'name' => null]);
});

test('until check-in opens, a good ticket shows as valid but isn’t marked as used', function () {
    config()->set('lri.headset_desk.check_in_open', false);
    $staff = User::factory()->deskStaff()->create();
    $ticket = gateTicket();

    $this->actingAs($staff)->get('/check-in')->assertSeeText('Practice mode');

    scanAtGate($ticket->code, $staff)->assertJson(['result' => 'valid_practice', 'valid' => true, 'heading' => 'Valid']);
    scanAtGate($ticket->code, $staff)->assertJson(['result' => 'valid_practice']);

    expect($ticket->fresh()->admitted_at)->toBeNull();
});

test('scans from anyone who can’t use the desk are refused, so the scanner can say they’re signed out', function (?User $person) {
    $ticket = gateTicket();

    scanAtGate($ticket->code, $person)->assertForbidden();

    expect($ticket->fresh()->admitted_at)->toBeNull();
})->with([
    'a guest' => fn (): ?User => null,
    'an admin without the desk' => fn (): User => User::factory()->withPermissions(AdminPermission::ManageOrders)->create(),
]);

test('a scan with no code is refused', function () {
    scanAtGate('', User::factory()->deskStaff()->create())->assertJsonValidationErrors('code');
});

test('the scanner’s home-screen app sends guests to sign in and back, staff to the scanner, and others to the website', function () {
    $staff = User::factory()->deskStaff()->create();

    $this->get(route('check-in.start'))->assertRedirect(route('filament.admin.auth.login'));
    Livewire::test(Login::class)
        ->fillForm(['email' => $staff->email, 'password' => 'password'])
        ->call('authenticate')
        ->assertRedirect(route('check-in.start'));

    $this->get(route('check-in.start'))->assertRedirect(route('check-in'));

    $this->actingAs(User::factory()->withPermissions(AdminPermission::ManageOrders)->create())
        ->get(route('check-in.start'))
        ->assertRedirect(route('home'));
});

test('phones can install the scanner as an app that opens on the scanner', function () {
    $this->get(route('check-in.manifest'))
        ->assertOk()
        ->assertHeader('Content-Type', 'application/manifest+json')
        ->assertJson(['start_url' => '/check-in/start', 'scope' => '/check-in', 'display' => 'standalone']);

    $this->get(route('check-in.service-worker'))
        ->assertOk()
        ->assertHeader('Content-Type', 'application/javascript; charset=utf-8')
        ->assertHeader('Service-Worker-Allowed', '/check-in');
});

test('the dashboard, orders and headset desk link to the scanner for people who can use the desk', function () {
    $this->actingAs(User::factory()->withPermissions(AdminPermission::ViewDashboard, AdminPermission::ManageOrders, AdminPermission::UseHeadsetDesk)->create());

    Livewire::test(Dashboard::class)->assertActionVisible('checkInScanner')->assertActionHasUrl('checkInScanner', route('check-in'));
    Livewire::test(ListOrders::class)->assertActionVisible('checkInScanner');
    Livewire::test(HeadsetDesk::class)->assertActionVisible('checkInScanner');

    $this->actingAs(User::factory()->withPermissions(AdminPermission::ViewDashboard, AdminPermission::ManageOrders)->create());

    Livewire::test(Dashboard::class)->assertActionHidden('checkInScanner');
    Livewire::test(ListOrders::class)->assertActionHidden('checkInScanner');
});

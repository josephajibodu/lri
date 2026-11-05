<?php

use App\Actions\Tickets\HandOverHeadset;
use App\Enums\AdminPermission;
use App\Exceptions\TicketNotUsable;
use App\Filament\Pages\HeadsetDesk;
use App\Mail\TicketMail;
use App\Models\Order;
use App\Models\Ticket;
use App\Models\User;
use Filament\Actions\Testing\TestAction;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Livewire\Features\SupportTesting\Testable;
use Livewire\Livewire;

uses(RefreshDatabase::class);

beforeEach(function () {
    Mail::fake();

    $this->staff = User::factory()->deskStaff()->create(['name' => 'Bisi Desk']);
    $this->order = Order::factory()->paid()->create(['buyer_name' => 'Ada Bello', 'buyer_email' => 'ada@example.com', 'quantity' => 2]);
    $this->named = Ticket::factory()->for($this->order)
        ->create(['ticket_type_id' => $this->order->ticket_type_id, 'holder_name' => 'Ada Bello', 'holder_email' => 'ada@example.com']);
    $this->blank = Ticket::factory()->for($this->order)->create(['ticket_type_id' => $this->order->ticket_type_id]);
});

/**
 * The headset desk page, as a desk staff member sees it.
 */
function desk(?User $user = null): Testable
{
    test()->actingAs($user ?? test()->staff);

    return Livewire::test(HeadsetDesk::class);
}

test('the desk is part of the admin and asks guests to sign in', function () {
    $this->get('/admin/headset-desk')->assertRedirect('/admin/login');
});

test('desk staff land on the desk and see nothing else in the admin', function () {
    $this->actingAs($this->staff)->get('/admin')->assertRedirect('/admin/headset-desk');

    $this->actingAs($this->staff)->get('/admin/headset-desk')
        ->assertOk()
        ->assertSeeText('Headset desk')
        ->assertDontSeeText('Orders')
        ->assertDontSeeText('Logs');

    $this->actingAs($this->staff)->get('/admin/dashboard')->assertForbidden();
    $this->actingAs($this->staff)->get('/admin/orders')->assertForbidden();
});

test('someone whose roles don’t include the desk can’t open it', function () {
    $this->actingAs(User::factory()->withPermissions(AdminPermission::ManageOrders)->create())
        ->get('/admin/headset-desk')
        ->assertForbidden();
});

test('the desk finds tickets by code with or without its dash, holder, or the buyer’s details', function (callable $search) {
    $other = Ticket::factory()->named()->create();

    desk()
        ->searchTable($search($this))
        ->assertCanSeeTableRecords([$this->named])
        ->assertCanNotSeeTableRecords([$other]);
})->with([
    'code' => [fn ($test): string => $test->named->code],
    'code without its dash' => [fn ($test): string => str_replace('-', '', strtolower($test->named->code))],
    'holder email' => [fn (): string => 'ada@example.com'],
    'order number' => [fn ($test): string => $test->order->reference],
]);

test('searching for the buyer brings up every ticket on their order, named or not', function () {
    desk()
        ->searchTable('Ada Bello')
        ->assertCanSeeTableRecords([$this->named, $this->blank]);
});

test('a scan opens the desk on that ticket', function () {
    $this->actingAs($this->staff)
        ->get($this->named->deskUrl())
        ->assertOk()
        ->assertSee($this->named->code);

    $this->actingAs($this->staff);
    Livewire::withQueryParams(['search' => $this->named->code])
        ->test(HeadsetDesk::class)
        ->assertCanSeeTableRecords([$this->named])
        ->assertCanNotSeeTableRecords([$this->blank]);
});

test('the desk shows each ticket as ready, already given, or needing a name with how many on its order are unnamed', function () {
    $this->named->update(['checked_in_at' => now(), 'checked_in_by' => $this->staff->id]);
    $ready = Ticket::factory()->named()->create();

    desk()
        ->assertSeeText('Paid, ready')
        ->assertSeeText('Headset already given')
        ->assertSeeText('by Bisi Desk')
        ->assertSeeText('Needs a name')
        ->assertSeeText('Order has 1 unnamed ticket')
        ->assertActionVisible(TestAction::make('giveHeadset')->table($ready))
        ->assertActionHidden(TestAction::make('giveHeadset')->table($this->named))
        ->assertActionHidden(TestAction::make('giveHeadset')->table($this->blank))
        ->assertActionVisible(TestAction::make('saveAndGiveHeadset')->table($this->blank));
});

test('staff name a blank ticket and give its headset in one step, and the person is emailed their ticket', function () {
    desk()
        ->callAction(TestAction::make('saveAndGiveHeadset')->table($this->blank), ['code' => $this->blank->code, 'name' => 'Tunde Okafor', 'email' => 'tunde@example.com'])
        ->assertHasNoFormErrors();

    expect($this->blank->fresh())
        ->holder_name->toBe('Tunde Okafor')
        ->holder_email->toBe('tunde@example.com')
        ->checked_in_by->toBe($this->staff->id);
    Mail::assertQueued(TicketMail::class, fn (TicketMail $mail): bool => $mail->hasTo('tunde@example.com'));
});

test('a blank ticket’s headset needs its code, so nobody gets one just by saying a buyer got them a ticket', function (string $typed, array $errors) {
    desk()
        ->callAction(TestAction::make('saveAndGiveHeadset')->table($this->blank), ['code' => $typed, 'name' => 'Stranger', 'email' => 'stranger@example.com'])
        ->assertHasFormErrors($errors);

    expect($this->blank->fresh())->holder_name->toBeNull()->checked_in_at->toBeNull();
})->with([
    'no code' => ['', ['code' => 'required']],
    'the wrong code' => ['AAAA-AAAA', ['code']],
]);

test('a blank ticket’s code can be typed in any case, with or without its dash', function () {
    $typed = str_replace('-', '', strtolower($this->blank->code));

    desk()
        ->callAction(TestAction::make('saveAndGiveHeadset')->table($this->blank), ['code' => $typed, 'name' => 'Tunde Okafor', 'email' => 'tunde@example.com'])
        ->assertHasNoFormErrors();

    expect($this->blank->fresh()->checked_in_at)->not->toBeNull();
});

test('a blank ticket’s code stays hidden from staff until it’s searched for in full', function () {
    desk()->assertDontSeeText($this->blank->code)->assertSeeText('••••-••••');

    desk()
        ->searchTable($this->blank->code)
        ->assertSeeText($this->blank->code)
        ->mountAction(TestAction::make('saveAndGiveHeadset')->table($this->blank))
        ->assertSchemaStateSet(['code' => $this->blank->code]);
});

test('a name at the desk can’t carry a link', function () {
    desk()
        ->callAction(TestAction::make('saveAndGiveHeadset')->table($this->blank), ['code' => $this->blank->code, 'name' => '[Renew](https://evil.example)', 'email' => 'tunde@example.com'])
        ->assertHasFormErrors(['name']);
});

test('the desk says when a ticket’s payment is being disputed, and offers nothing', function () {
    $this->order->update(['status' => 'disputed']);

    desk()
        ->assertActionHidden(TestAction::make('giveHeadset')->table($this->named))
        ->assertActionHidden(TestAction::make('saveAndGiveHeadset')->table($this->blank))
        ->searchTable($this->named->code)
        ->assertSeeText('Payment disputed');
});

test('staff can fix a name at the desk before giving the headset', function () {
    desk()
        ->callAction(TestAction::make('changeName')->table($this->named), ['name' => 'Ada B. Bello', 'email' => 'ada@example.com'])
        ->assertHasNoFormErrors();

    expect($this->named->fresh())
        ->holder_name->toBe('Ada B. Bello')
        ->checked_in_at->toBeNull();
});

test('naming at the desk needs a name and a real email, and gives no headset until it has them', function () {
    desk()
        ->callAction(TestAction::make('saveAndGiveHeadset')->table($this->blank), ['name' => '', 'email' => 'nope'])
        ->assertHasFormErrors(['name' => 'required', 'email' => 'email']);

    expect($this->blank->fresh())->holder_name->toBeNull()->checked_in_at->toBeNull();
});

test('handing over a headset records when and by whom, and only once', function () {
    $this->freezeSecond();

    desk()->callAction(TestAction::make('giveHeadset')->table($this->named));

    expect($this->named->fresh())
        ->checked_in_at->toEqual(now())
        ->checked_in_by->toBe($this->staff->id);

    desk()
        ->assertActionHidden(TestAction::make('giveHeadset')->table($this->named))
        ->assertActionHidden(TestAction::make('changeName')->table($this->named));
});

test('a headset is refused for a ticket already collected, a refunded one, or one with no name', function (callable $arrange, string $message) {
    $ticket = $arrange($this);

    expect(fn () => app(HandOverHeadset::class)->handle($ticket, $this->staff))
        ->toThrow(TicketNotUsable::class, $message);
    expect($ticket->fresh()->checked_in_by)->not->toBe($this->staff->id);
})->with([
    'already collected' => [fn ($test): Ticket => tap($test->named)->update(['checked_in_at' => now()]), 'already collected'],
    'refunded' => [fn ($test): Ticket => tap($test->named, fn () => $test->order->update(['status' => 'refunded'])), 'isn’t paid, or was refunded'],
    'no name' => [fn ($test): Ticket => $test->blank, 'Fill in this person’s name'],
]);

test('the desk offers nothing for a refunded order’s tickets', function () {
    $this->order->update(['status' => 'refunded']);

    desk()
        ->assertActionHidden(TestAction::make('giveHeadset')->table($this->named))
        ->assertActionHidden(TestAction::make('saveAndGiveHeadset')->table($this->blank))
        ->searchTable($this->named->code)
        ->assertSeeText('Not paid');
});

test('the desk counts the headsets handed out against the paid tickets', function () {
    $this->named->update(['checked_in_at' => now()]);
    Ticket::factory()->named()->for(Order::factory()->create(['status' => 'refunded']))->create();

    desk()->assertSeeText('1 of 2 headsets handed out');
});

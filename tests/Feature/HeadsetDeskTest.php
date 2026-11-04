<?php

use App\Actions\Tickets\HandOverHeadset;
use App\Exceptions\TicketNotUsable;
use App\Filament\Desk\Pages\HeadsetDesk;
use App\Filament\Resources\Users\Pages\ManageUsers;
use App\Mail\TicketMail;
use App\Models\Order;
use App\Models\Ticket;
use App\Models\User;
use Filament\Actions\Testing\TestAction;
use Filament\Facades\Filament;
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
    Filament::setCurrentPanel('desk');

    return Livewire::test(HeadsetDesk::class);
}

test('the desk asks guests to sign in', function () {
    $this->get('/desk')->assertRedirect('/desk/login');
});

test('desk staff can use the desk but not the admin or the logs', function () {
    $this->actingAs($this->staff)->get('/desk')->assertOk()->assertSeeText('Headset desk');

    $this->actingAs($this->staff)->get('/admin')->assertForbidden();
    $this->actingAs($this->staff)->get('/admin/orders')->assertForbidden();
    $this->actingAs($this->staff)->get('/log-viewer')->assertForbidden();
});

test('organisers can use the desk as well as the admin', function () {
    $organiser = User::factory()->create();

    $this->actingAs($organiser)->get('/desk')->assertOk();
    $this->actingAs($organiser)->get('/admin')->assertOk();
});

test('organisers can make an account that only works the desk', function () {
    $this->actingAs(User::factory()->create());

    Livewire::test(ManageUsers::class)
        ->callAction('create', ['name' => 'Kemi Desk', 'email' => 'kemi@example.com', 'role' => 'desk_staff', 'password' => 'a-long-password-1'])
        ->assertHasNoFormErrors();

    expect(User::query()->where('email', 'kemi@example.com')->firstOrFail()->isOrganiser())->toBeFalse();
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

test('staff fill in a blank ticket’s name at the desk and the person is emailed their ticket', function () {
    desk()
        ->assertActionHidden(TestAction::make('handOver')->table($this->blank))
        ->callAction(TestAction::make('fillInName')->table($this->blank), ['name' => 'Tunde Okafor', 'email' => 'tunde@example.com'])
        ->assertHasNoFormErrors();

    expect($this->blank->fresh())
        ->holder_name->toBe('Tunde Okafor')
        ->holder_email->toBe('tunde@example.com');
    Mail::assertQueued(TicketMail::class, fn (TicketMail $mail): bool => $mail->hasTo('tunde@example.com'));
});

test('filling in a name at the desk needs a name and a real email', function () {
    desk()
        ->callAction(TestAction::make('fillInName')->table($this->blank), ['name' => '', 'email' => 'nope'])
        ->assertHasFormErrors(['name' => 'required', 'email' => 'email']);

    expect($this->blank->fresh()->holder_name)->toBeNull();
});

test('handing over a headset records when and by whom, and only once', function () {
    $this->freezeSecond();

    desk()->callAction(TestAction::make('handOver')->table($this->named));

    expect($this->named->fresh())
        ->checked_in_at->toEqual(now())
        ->checked_in_by->toBe($this->staff->id);

    desk()
        ->assertActionHidden(TestAction::make('handOver')->table($this->named))
        ->assertActionHidden(TestAction::make('fillInName')->table($this->named));
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
        ->assertActionHidden(TestAction::make('handOver')->table($this->named))
        ->assertActionHidden(TestAction::make('fillInName')->table($this->blank))
        ->searchTable($this->named->code)
        ->assertSeeText('Not paid');
});

test('the desk counts the headsets handed out against the paid tickets', function () {
    $this->named->update(['checked_in_at' => now()]);
    Ticket::factory()->named()->for(Order::factory()->create(['status' => 'refunded']))->create();

    desk()->assertSeeText('1 of 2 headsets handed out');
});

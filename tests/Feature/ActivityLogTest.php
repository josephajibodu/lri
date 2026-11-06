<?php

use App\Actions\AdminInvitations\AnswerAdminInvitation;
use App\Actions\Tickets\NameTicket;
use App\Enums\AdminPermission;
use App\Enums\OrderStatus;
use App\Enums\PaymentMethod;
use App\Enums\StaffRole;
use App\Filament\Pages\HeadsetDesk;
use App\Filament\Resources\Activities\Pages\ManageActivities;
use App\Filament\Resources\AdminInvitations\Pages\ManageAdminInvitations;
use App\Filament\Resources\Coupons\Pages\ManageCoupons;
use App\Filament\Resources\EmailToolSends\Pages\ManageEmailToolSends;
use App\Filament\Resources\Orders\Pages\ListOrders;
use App\Filament\Resources\Orders\Pages\ViewOrder;
use App\Filament\Resources\PaymentChannels\Pages\ManagePaymentChannels;
use App\Filament\Resources\Roles\Pages\ManageRoles;
use App\Filament\Resources\TicketTypes\Pages\EditTicketType;
use App\Filament\Resources\TicketTypes\Pages\ListTicketTypes;
use App\Filament\Resources\Users\Pages\ManageUsers;
use App\Filament\Resources\WebhookCalls\Pages\ManageWebhookCalls;
use App\Filament\Widgets\RecentActivity;
use App\Jobs\SendToEmailTool;
use App\Models\Activity;
use App\Models\AdminInvitation;
use App\Models\Coupon;
use App\Models\EmailToolSend;
use App\Models\Order;
use App\Models\PaymentChannel;
use App\Models\Ticket;
use App\Models\TicketType;
use App\Models\User;
use App\Models\WebhookCall;
use App\Support\AdminActivity;
use Filament\Actions\Testing\TestAction;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Bus;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;
use Livewire\Livewire;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;

uses(RefreshDatabase::class);

function latestLogLine(): Activity
{
    return Activity::query()->latest('id')->firstOrFail();
}

/**
 * Everything saved in the log, as plain text, to check that a secret isn't anywhere in it.
 */
function everythingInTheLog(): string
{
    return DB::table('activity_log')->get()
        ->map(fn (object $line): string => $line->description.' '.$line->causer_name.' '.json_encode(
            [json_decode((string) $line->attribute_changes), json_decode((string) $line->properties)],
            JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE,
        ))
        ->join("\n");
}

test('a super admin can read the activity log', function () {
    $this->actingAs(User::factory()->superAdmin()->create());
    AdminActivity::record('Downloaded the orders list as a spreadsheet');

    $this->get('/admin/activity-log')
        ->assertOk()
        ->assertSeeText('Downloaded the orders list as a spreadsheet');
});

test('nobody but a super admin can open the activity log', function (User $person) {
    $this->actingAs($person)->get('/admin/activity-log')->assertForbidden();
})->with([
    'an admin' => fn () => User::factory()->withRole(StaffRole::Admin)->create(),
    'a developer, who can see the technical logs' => fn () => User::factory()->withRole(StaffRole::Developer)->create(),
    'someone who manages people and roles' => fn () => User::factory()->withPermissions(AdminPermission::ManagePeople)->create(),
    'headset desk staff' => fn () => User::factory()->deskStaff()->create(),
]);

test('someone signed out is sent to sign in instead of seeing the activity log', function () {
    $this->get('/admin/activity-log')->assertRedirect('/admin/login');
});

test('the activity log is in a super admin’s menu', function () {
    $this->actingAs(User::factory()->superAdmin()->create())->get('/admin/orders')->assertSeeText('Activity log');
});

test('the activity log isn’t in anyone else’s menu', function () {
    $this->actingAs(User::factory()->withRole(StaffRole::Developer)->create())->get('/admin/webhook-calls')->assertDontSeeText('Activity log');
});

test('nobody can add to, change or remove from the log, not even a super admin', function () {
    $superAdmin = User::factory()->superAdmin()->create();
    $this->actingAs($superAdmin);
    AdminActivity::record('Downloaded the orders list as a spreadsheet');
    $line = latestLogLine();

    expect($superAdmin->can('create', Activity::class))->toBeFalse()
        ->and($superAdmin->can('update', $line))->toBeFalse()
        ->and($superAdmin->can('delete', $line))->toBeFalse()
        ->and($superAdmin->can('deleteAny', Activity::class))->toBeFalse();

    Livewire::test(ManageActivities::class)
        ->assertActionDoesNotExist('create')
        ->assertActionDoesNotExist(TestAction::make('edit')->table($line))
        ->assertActionDoesNotExist(TestAction::make('delete')->table($line));
});

test('recent activity is on the dashboard for a super admin only', function () {
    $this->actingAs(User::factory()->superAdmin()->create())
        ->get('/admin/dashboard')
        ->assertOk()
        ->assertSeeLivewire(RecentActivity::class);

    $this->actingAs(User::factory()->withPermissions(AdminPermission::ViewDashboard)->create())
        ->get('/admin/dashboard')
        ->assertOk()
        ->assertDontSeeLivewire(RecentActivity::class);
});

test('recent activity shows only the latest ten lines', function () {
    $this->actingAs(User::factory()->superAdmin()->create());

    foreach (range(1, 11) as $number) {
        AdminActivity::record("Thing number {$number} done");
    }

    Livewire::test(RecentActivity::class)
        ->assertSeeText('Thing number 11 done')
        ->assertSeeText('Thing number 2 done')
        ->assertDontSeeText('Thing number 1 done');
});

test('changing a ticket type records who did it and the price before and after', function () {
    $ticketType = TicketType::factory()->create(['name' => 'Early bird', 'price' => 500000, 'currency' => 'NGN']);
    $organiser = User::factory()->withPermissions(AdminPermission::ManageTicketTypes)->create(['name' => 'Ada Organiser']);
    $this->actingAs($organiser);

    Livewire::test(EditTicketType::class, ['record' => $ticketType->getRouteKey()])
        ->fillForm(['price' => '7500'])
        ->call('save')
        ->assertHasNoFormErrors();

    $line = latestLogLine();
    expect($line->description)->toBe('Changed ticket type "Early bird"')
        ->and($line->causer_id)->toBe($organiser->id)
        ->and($line->causer_name)->toBe('Ada Organiser')
        ->and($line->subject_id)->toBe($ticketType->id)
        ->and($line->changeRows())->toBe([['field' => 'Price', 'before' => '₦5,000', 'after' => '₦7,500']]);
});

test('each kind of record an admin can change is recorded', function (Model $model, array $change, string $field) {
    $this->actingAs(User::factory()->superAdmin()->create());

    $model->forceFill($change)->save();

    $line = latestLogLine();
    expect($line->subject->is($model))->toBeTrue()
        ->and(array_column($line->changeRows(), 'field'))->toBe([$field]);
})->with([
    'an order' => [fn () => Order::factory()->create(), ['review_note' => 'Looks odd'], 'Review note'],
    'a ticket' => [fn () => Ticket::factory()->create(), ['holder_name' => 'Kemi Ade'], 'Holder name'],
    'a coupon' => [fn () => Coupon::factory()->create(), ['disabled_at' => now()], 'Disabled at'],
    'a way to pay' => [fn () => PaymentChannel::factory()->create(['is_featured' => false]), ['is_featured' => true], 'Is featured'],
    'a person' => [fn () => User::factory()->create(), ['email' => 'kemi@example.com'], 'Email'],
]);

test('a new or deleted record is recorded, and its values can still be read afterwards', function () {
    $this->actingAs(User::factory()->superAdmin()->create());

    $ticketType = TicketType::factory()->create(['name' => 'Early bird', 'price' => 500000, 'currency' => 'NGN']);
    $created = latestLogLine();
    $ticketType->delete();
    $deleted = latestLogLine();

    expect($created->description)->toBe('Created ticket type "Early bird"')
        ->and(collect($created->changeRows())->firstWhere('field', 'Price'))->toBe(['field' => 'Price', 'before' => '–', 'after' => '₦5,000'])
        ->and($deleted->description)->toBe('Deleted ticket type "Early bird"')
        ->and(collect($deleted->changeRows())->firstWhere('field', 'Price'))->toBe(['field' => 'Price', 'before' => '₦5,000', 'after' => '–']);
});

test('switching a toggle in a table is recorded', function () {
    $channel = PaymentChannel::factory()->create(['is_enabled' => true]);
    $this->actingAs(User::factory()->withPermissions(AdminPermission::ManagePaymentMethods)->create());

    Livewire::test(ManagePaymentChannels::class)->call('updateTableColumnState', 'is_enabled', (string) $channel->id, false);

    expect(latestLogLine()->changeRows())->toBe([['field' => 'Is enabled', 'before' => 'Yes', 'after' => 'No']]);
});

test('dragging things into a new order is recorded', function (string $page, Collection $records, AdminPermission $permission, string $description) {
    $this->actingAs(User::factory()->withPermissions($permission)->create());

    Livewire::test($page)->call('reorderTable', $records->pluck('id')->reverse()->values()->all());

    expect(latestLogLine()->description)->toBe($description);
})->with([
    'ticket types' => [ListTicketTypes::class, fn () => TicketType::factory()->count(2)->create(), AdminPermission::ManageTicketTypes, 'Changed the order of the ticket types'],
    'ways to pay' => [ManagePaymentChannels::class, fn () => collect([
        PaymentChannel::factory()->method(PaymentMethod::BankTransfer)->create(),
        PaymentChannel::factory()->method(PaymentMethod::Card)->create(),
    ]), AdminPermission::ManagePaymentMethods, 'Changed the order of the ways to pay'],
]);

test('changes with nobody signed in are left out', function () {
    $order = Order::factory()->create();

    $order->update(['status' => OrderStatus::Paid]);

    $this->assertDatabaseCount('activity_log', 0);
});

test('a change only to something the log doesn’t track adds no line', function () {
    $order = Order::factory()->create();
    $this->actingAs(User::factory()->superAdmin()->create());

    $order->update(['attribution' => ['source' => 'instagram']]);

    $this->assertDatabaseCount('activity_log', 0);
});

test('a password change says it changed without saving the password', function () {
    $person = User::factory()->withRole(StaffRole::Admin)->create(['name' => 'Kemi Ade']);
    $this->actingAs(User::factory()->superAdmin()->create());

    Livewire::test(ManageUsers::class)
        ->callAction(TestAction::make('edit')->table($person), ['password' => 'a-brand-new-password'])
        ->assertHasNoActionErrors();

    expect(latestLogLine()->description)->toBe('Changed the password of admin Kemi Ade')
        ->and(everythingInTheLog())
        ->not->toContain('a-brand-new-password')
        ->not->toContain($person->fresh()->password);
});

test('a sign-in cookie’s secret is never recorded', function () {
    $person = User::factory()->create();
    $this->actingAs(User::factory()->superAdmin()->create());

    $person->forceFill(['remember_token' => 'a-secret-remember-token'])->save();

    $this->assertDatabaseCount('activity_log', 0);
});

test('a ticket’s secret link is never recorded when it moves to someone else', function () {
    Mail::fake();
    $order = Order::factory()->paid()->create();
    $ticket = Ticket::factory()->for($order)->create(['ticket_type_id' => $order->ticket_type_id, 'holder_name' => 'Ada Bello', 'holder_email' => 'ada@example.com']);
    $oldLink = $ticket->claim_token;
    $this->actingAs(User::factory()->deskStaff()->create());

    app(NameTicket::class)->handle($ticket, 'Kemi Ade', 'kemi@example.com');

    expect(collect(latestLogLine()->changeRows())->pluck('field')->all())->toContain('Holder name', 'Holder email', 'Code')
        ->and(everythingInTheLog())
        ->toContain('kemi@example.com')
        ->not->toContain($oldLink)
        ->not->toContain($ticket->fresh()->claim_token);
});

test('an order’s secret receipt link is never recorded', function () {
    $this->actingAs(User::factory()->superAdmin()->create());

    $order = Order::factory()->create();

    expect(latestLogLine()->description)->toBe("Created order {$order->reference}")
        ->and(everythingInTheLog())->not->toContain($order->receipt_token);
});

test('text in a name can’t pull a secret into the log', function () {
    $person = User::factory()->create(['name' => ':subject.password']);
    $this->actingAs(User::factory()->superAdmin()->create());

    $person->update(['email' => 'kemi@example.com']);

    expect(latestLogLine()->description)->toBe('Changed admin :subject.password');
});

test('what people typed is shown as plain text, not run as code', function () {
    $ticketType = TicketType::factory()->create(['name' => '<script>alert(1)</script>']);
    $this->actingAs(User::factory()->superAdmin()->create());
    $ticketType->update(['description' => '<img src=x onerror=alert(2)>']);

    $this->get('/admin/activity-log')
        ->assertOk()
        ->assertDontSee('<script>alert(1)</script>', false)
        ->assertDontSee('<img src=x onerror=alert(2)>', false)
        ->assertSee('&lt;script&gt;alert(1)&lt;/script&gt;', false);
});

test('changing someone’s roles records the roles before and after', function () {
    $person = User::factory()->deskStaff()->create(['name' => 'Kemi Ade']);
    $this->actingAs(User::factory()->superAdmin()->create());

    Livewire::test(ManageUsers::class)
        ->callAction(TestAction::make('edit')->table($person), ['roles' => [Role::findByName(StaffRole::Admin->value)->id]])
        ->assertHasNoActionErrors();

    expect(latestLogLine()->description)->toBe('Changed the roles of admin Kemi Ade')
        ->and(latestLogLine()->changeRows())->toBe([['field' => 'Roles', 'before' => 'Headset desk', 'after' => 'Admin']]);
});

test('saving someone without touching their roles records nothing about roles', function () {
    $person = User::factory()->deskStaff()->create(['name' => 'Kemi Ade']);
    $this->actingAs(User::factory()->superAdmin()->create());

    Livewire::test(ManageUsers::class)
        ->callAction(TestAction::make('edit')->table($person), ['name' => 'Kemi Adeyemi'])
        ->assertHasNoActionErrors();

    expect(Activity::query()->pluck('description')->all())->toBe(['Changed admin Kemi Adeyemi']);
});

test('adding someone without an invite records them and their roles, but not their password', function () {
    $this->actingAs(User::factory()->superAdmin()->create());

    Livewire::test(ManageUsers::class)
        ->callAction('create', [
            'name' => 'Kemi Ade',
            'email' => 'kemi@example.com',
            'roles' => [Role::findByName(StaffRole::Admin->value)->id],
            'password' => 'a-brand-new-password',
        ])
        ->assertHasNoActionErrors();

    expect(Activity::query()->orderBy('id')->pluck('description')->all())->toBe(['Created admin Kemi Ade', 'Changed the roles of admin Kemi Ade'])
        ->and(latestLogLine()->changeRows())->toBe([['field' => 'Roles', 'before' => '–', 'after' => 'Admin']])
        ->and(everythingInTheLog())->not->toContain('a-brand-new-password');
});

test('creating, changing and deleting a role records what it can see', function () {
    $this->actingAs(User::factory()->superAdmin()->create());
    $permissionId = fn (AdminPermission $permission): int => Permission::findByName($permission->value)->id;

    Livewire::test(ManageRoles::class)
        ->callAction('create', ['name' => 'Desk lead', 'permissions' => [$permissionId(AdminPermission::UseHeadsetDesk)]])
        ->assertHasNoActionErrors();

    expect(latestLogLine()->description)->toBe('Created role "Desk lead"')
        ->and(latestLogLine()->changeRows())->toBe([
            ['field' => 'Name', 'before' => '–', 'after' => 'Desk lead'],
            ['field' => 'Can see', 'before' => '–', 'after' => 'Headset desk'],
        ]);

    $role = Role::findByName('Desk lead');
    Livewire::test(ManageRoles::class)
        ->callAction(TestAction::make('edit')->table($role), ['permissions' => [$permissionId(AdminPermission::UseHeadsetDesk), $permissionId(AdminPermission::ManageOrders)]])
        ->assertHasNoActionErrors();

    expect(latestLogLine()->description)->toBe('Changed role "Desk lead"')
        ->and(latestLogLine()->changeRows())->toBe([['field' => 'Can see', 'before' => 'Headset desk', 'after' => 'Headset desk, Orders']]);

    Livewire::test(ManageRoles::class)->callAction(TestAction::make('delete')->table($role->fresh()));

    expect(latestLogLine()->description)->toBe('Deleted role "Desk lead"')
        ->and(latestLogLine()->changeRows())->toBe([
            ['field' => 'Name', 'before' => 'Desk lead', 'after' => '–'],
            ['field' => 'Can see', 'before' => 'Headset desk, Orders', 'after' => '–'],
        ]);
});

test('inviting someone, sending a new link and cancelling are each recorded, without the link', function () {
    Mail::fake();
    $this->actingAs(User::factory()->superAdmin()->create());

    Livewire::test(ManageAdminInvitations::class)
        ->callAction('invite', ['email' => 'kemi@example.com', 'roles' => [Role::findByName(StaffRole::Admin->value)->id]])
        ->assertHasNoActionErrors();

    expect(latestLogLine()->description)->toBe('Invited kemi@example.com to the admin')
        ->and(latestLogLine()->changeRows())->toBe([['field' => 'Roles', 'before' => '–', 'after' => 'Admin']]);

    $invitation = AdminInvitation::query()->sole();
    Livewire::test(ManageAdminInvitations::class)->callAction(TestAction::make('sendAgain')->table($invitation));
    expect(latestLogLine()->description)->toBe('Sent kemi@example.com a new invitation link');

    Livewire::test(ManageAdminInvitations::class)->callAction(TestAction::make('cancelInvitation')->table($invitation));
    expect(latestLogLine()->description)->toBe('Cancelled the invitation to kemi@example.com')
        ->and(everythingInTheLog())->not->toContain($invitation->fresh()->getRawOriginal('token'));
});

test('accepting an invitation is recorded under the new person, with the access they got', function () {
    $invitation = AdminInvitation::factory()->create(['email' => 'kemi@example.com']);
    $invitation->roles()->sync([Role::findByName(StaffRole::Admin->value)->id]);

    $person = app(AnswerAdminInvitation::class)->accept($invitation, 'Kemi Ade', 'a-brand-new-password');

    $line = latestLogLine();
    expect($line->description)->toBe('Accepted their invitation and joined the admin')
        ->and($line->causer_id)->toBe($person->id)
        ->and($line->causer_name)->toBe('Kemi Ade')
        ->and($line->changeRows())->toBe([['field' => 'Roles', 'before' => '–', 'after' => 'Admin']])
        ->and(everythingInTheLog())->not->toContain('a-brand-new-password');
});

test('emailing tickets again is recorded as one clear line', function () {
    Mail::fake();
    $order = Order::factory()->paid()->create(['buyer_email' => 'ada@example.com']);
    $this->actingAs(User::factory()->withPermissions(AdminPermission::ManageOrders)->create());

    Livewire::test(ViewOrder::class, ['record' => $order->getRouteKey()])->callAction('resendTickets');

    expect(Activity::query()->pluck('description')->all())->toBe(["Emailed the tickets for order {$order->reference} again, to ada@example.com"]);
});

test('marking a test payment shows the order’s status in words', function () {
    $order = Order::factory()->paid()->create();
    $this->actingAs(User::factory()->withPermissions(AdminPermission::ManageOrders)->create());

    Livewire::test(ViewOrder::class, ['record' => $order->getRouteKey()])->callAction('markAsTestPayment');

    expect(collect(latestLogLine()->changeRows())->firstWhere('field', 'Status'))
        ->toBe(['field' => 'Status', 'before' => 'Paid', 'after' => 'Test payment']);
});

test('checking an order with the provider is recorded', function () {
    fakeBachs();
    $ticketType = TicketType::factory()->create(['price' => 1000000]);
    $channel = PaymentChannel::factory()->method(PaymentMethod::BankTransfer)->create();
    $this->post(route('orders.store'), ['ticket_type' => $ticketType->slug, 'quantity' => 1, 'name' => 'Ada', 'email' => 'ada@example.com', 'channel' => $channel->id]);
    $order = Order::query()->sole();
    fakePaidBachsCheckout($order->latestPayment);
    $this->actingAs(User::factory()->withPermissions(AdminPermission::ManageOrders)->create());

    Livewire::test(ViewOrder::class, ['record' => $order->getRouteKey()])->callAction('checkWithProvider');

    expect(Activity::query()->pluck('description')->all())->toContain("Checked order {$order->reference} with the payment provider");
});

test('downloading the orders list is recorded', function () {
    $this->actingAs(User::factory()->withPermissions(AdminPermission::ManageOrders)->create());

    Livewire::test(ListOrders::class)->callAction('export');

    expect(latestLogLine()->description)->toBe('Downloaded the orders list as a spreadsheet');
});

test('sending to the email tool again is recorded', function () {
    Bus::fake([SendToEmailTool::class]);
    $send = EmailToolSend::factory()->failed()->create();
    $this->actingAs(User::factory()->withPermissions(AdminPermission::ManageEmailTool)->create());

    Livewire::test(ManageEmailToolSends::class)->callAction(TestAction::make('sendAgain')->table($send));

    expect(latestLogLine()->description)->toBe("Sent the news about order {$send->order->reference} to the email tool again");
});

test('handling a webhook again is recorded, whether or not it works', function () {
    $call = WebhookCall::factory()->create(['provider' => 'bachs', 'type' => 'collection.succeeded']);
    $this->actingAs(User::factory()->withPermissions(AdminPermission::ManageWebhooks)->create());

    Livewire::test(ManageWebhookCalls::class)->callAction(TestAction::make('replay')->table($call));

    expect(Activity::query()->pluck('description')->all())->toContain('Ran the bachs webhook "collection.succeeded" again');
});

test('a coupon limited to certain tickets records which ones', function () {
    $ticketType = TicketType::factory()->create(['name' => 'Early bird']);
    $this->actingAs(User::factory()->withPermissions(AdminPermission::ManageCoupons)->create());

    Livewire::test(ManageCoupons::class)
        ->callAction('create', [
            'code' => 'LRI20',
            'discount_type' => 'percentage',
            'discount_value' => 20,
            'limit_ticket_types' => true,
            'ticketTypes' => [$ticketType->id],
        ])
        ->assertHasNoActionErrors();

    expect(Activity::query()->orderBy('id')->pluck('description')->all())->toBe(['Created coupon LRI20', 'Limited coupon LRI20 to certain tickets'])
        ->and(latestLogLine()->changeRows())->toBe([['field' => 'Works on', 'before' => '–', 'after' => 'Early bird']]);
});

test('handing over a headset is recorded under the desk staff member, with the time in Lagos', function () {
    Mail::fake();
    config()->set('lri.headset_desk.open', true);
    $this->travelTo('2026-11-06 10:00:00');
    $order = Order::factory()->paid()->create();
    $ticket = Ticket::factory()->for($order)->create(['ticket_type_id' => $order->ticket_type_id, 'holder_name' => 'Ada Bello', 'holder_email' => 'ada@example.com']);
    $staff = User::factory()->deskStaff()->create(['name' => 'Bisi Desk']);
    $this->actingAs($staff);

    Livewire::test(HeadsetDesk::class)->callAction(TestAction::make('giveHeadset')->table($ticket));

    $line = latestLogLine();
    expect($line->description)->toBe("Changed ticket {$ticket->code}")
        ->and($line->causer_name)->toBe('Bisi Desk')
        ->and($line->changeRows())->toBe([['field' => 'Checked in at', 'before' => '–', 'after' => '6 Nov 2026, 11:00 AM']]);
});

test('a line still names the person, and can be found by them, after their login is removed', function () {
    $coupon = Coupon::factory()->create(['code' => 'LRI20']);
    $organiser = User::factory()->withPermissions(AdminPermission::ManageCoupons)->create(['name' => 'Ada Organiser']);
    $this->actingAs($organiser);
    $coupon->forceFill(['disabled_at' => now()])->save();
    $line = latestLogLine();

    $this->actingAs(User::factory()->superAdmin()->create());
    $organiser->delete();

    expect($line->fresh()->causer)->toBeNull();

    Livewire::test(ManageActivities::class)
        ->filterTable('causer_id', $organiser->id)
        ->assertCanSeeTableRecords([$line])
        ->assertSeeText('Ada Organiser');
});

test('the log can be narrowed down to one person and one part of the admin', function () {
    $ada = User::factory()->withPermissions(AdminPermission::ManageCoupons)->create(['name' => 'Ada Organiser']);
    $bisi = User::factory()->withPermissions(AdminPermission::ManageCoupons)->create(['name' => 'Bisi Desk']);
    $coupon = Coupon::factory()->create();
    $order = Order::factory()->create();
    $this->actingAs($ada);
    AdminActivity::record('Ada on an order', $order);
    $adasOrder = latestLogLine();
    $this->actingAs($bisi);
    AdminActivity::record('Bisi on a coupon', $coupon);
    $bisisCoupon = latestLogLine();
    $this->actingAs(User::factory()->superAdmin()->create());

    Livewire::test(ManageActivities::class)
        ->filterTable('causer_id', $ada->id)
        ->assertCanSeeTableRecords([$adasOrder])
        ->assertCanNotSeeTableRecords([$bisisCoupon]);

    Livewire::test(ManageActivities::class)
        ->filterTable('subject_type', Coupon::class)
        ->assertCanSeeTableRecords([$bisisCoupon])
        ->assertCanNotSeeTableRecords([$adasOrder]);
});

test('the log’s dates mean days in Lagos', function () {
    $this->actingAs(User::factory()->superAdmin()->create());
    $this->travelTo('2026-11-05 22:30:00');
    AdminActivity::record('Late on the 5th in Lagos');
    $lateOnThe5th = latestLogLine();
    $this->travelTo('2026-11-05 23:30:00');
    AdminActivity::record('Early on the 6th in Lagos');
    $earlyOnThe6th = latestLogLine();

    Livewire::test(ManageActivities::class)
        ->filterTable('period', ['from' => '2026-11-06', 'until' => '2026-11-06'])
        ->assertCanSeeTableRecords([$earlyOnThe6th])
        ->assertCanNotSeeTableRecords([$lateOnThe5th]);
});

test('a line opens to show what changed', function () {
    $ticketType = TicketType::factory()->create();
    $this->actingAs(User::factory()->superAdmin()->create(['name' => 'Ada Organiser']));
    $ticketType->update(['description' => 'For the press']);
    $line = latestLogLine();

    Livewire::test(ManageActivities::class)
        ->mountAction(TestAction::make('view')->table($line))
        ->assertMountedActionModalSee(['What changed', 'Description', 'For the press', 'Ada Organiser']);
});

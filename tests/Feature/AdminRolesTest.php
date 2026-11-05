<?php

use App\Enums\AdminPermission;
use App\Enums\StaffRole;
use App\Filament\Resources\Roles\Pages\ManageRoles;
use App\Filament\Resources\Users\Pages\ManageUsers;
use App\Models\User;
use Filament\Actions\Testing\TestAction;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;

uses(RefreshDatabase::class);

test('each part of the admin opens only for roles that include it', function (string $url, AdminPermission $permission) {
    config()->set('lri.barracks_registration.open', true);
    $other = $permission === AdminPermission::ManageOrders ? AdminPermission::ManageBirthdays : AdminPermission::ManageOrders;

    $this->actingAs(User::factory()->withPermissions($permission)->create())->get($url)->assertOk();
    $this->actingAs(User::factory()->withPermissions($other)->create())->get($url)->assertForbidden();
})->with([
    'dashboard' => ['/admin/dashboard', AdminPermission::ViewDashboard],
    'orders' => ['/admin/orders', AdminPermission::ManageOrders],
    'headset desk' => ['/admin/headset-desk', AdminPermission::UseHeadsetDesk],
    'ticket types' => ['/admin/ticket-types', AdminPermission::ManageTicketTypes],
    'coupons' => ['/admin/coupons', AdminPermission::ManageCoupons],
    'ways to pay' => ['/admin/payment-channels', AdminPermission::ManagePaymentMethods],
    'birthdays' => ['/admin/birthdays', AdminPermission::ManageBirthdays],
    'barracks permits' => ['/admin/barracks-registrations', AdminPermission::ManageBarracksRegistrations],
    'people' => ['/admin/users', AdminPermission::ManagePeople],
    'invitations' => ['/admin/invitations', AdminPermission::ManagePeople],
    'roles' => ['/admin/roles', AdminPermission::ManagePeople],
    'webhooks' => ['/admin/webhook-calls', AdminPermission::ManageWebhooks],
    'email tool' => ['/admin/email-tool-sends', AdminPermission::ManageEmailTool],
    'logs' => ['/log-viewer', AdminPermission::ViewLogs],
]);

test('a super admin can open every part of the admin', function () {
    $this->actingAs(User::factory()->superAdmin()->create());

    foreach (['/admin/dashboard', '/admin/orders', '/admin/headset-desk', '/admin/users', '/admin/roles', '/log-viewer'] as $url) {
        $this->get($url)->assertOk();
    }
});

test('someone with no role can’t get into the admin', function () {
    $this->actingAs(User::factory()->create())->get('/admin/orders')->assertForbidden();
});

test('the admin and its menu only show what someone’s roles allow', function () {
    $this->actingAs(User::factory()->withPermissions(AdminPermission::ManageOrders, AdminPermission::ManageBirthdays)->create())
        ->get('/admin/orders')
        ->assertOk()
        ->assertSeeText('Birthdays')
        ->assertDontSeeText('Headset desk')
        ->assertDontSeeText('Ticket types')
        ->assertDontSeeText('Logs');
});

test('a new role’s parts of the admin open as soon as it’s saved and given to someone', function () {
    $this->actingAs(User::factory()->superAdmin()->create());
    $volunteer = User::factory()->deskStaff()->create();

    $this->actingAs($volunteer)->get('/admin/orders')->assertForbidden();

    $this->actingAs(User::factory()->superAdmin()->create());
    Livewire::test(ManageRoles::class)
        ->callAction('create', [
            'name' => 'Desk lead',
            'permissions' => Permission::query()
                ->whereIn('name', [AdminPermission::UseHeadsetDesk->value, AdminPermission::ManageOrders->value])
                ->pluck('id')
                ->all(),
        ])
        ->assertHasNoFormErrors();

    $volunteer->syncRoles(['Desk lead']);

    $this->actingAs($volunteer->fresh())->get('/admin/orders')->assertOk();
});

test('changing a role’s parts of the admin takes effect straight away', function () {
    $volunteer = User::factory()->deskStaff()->create();
    $deskRole = Role::findByName(StaffRole::HeadsetDesk->value);

    $this->actingAs(User::factory()->superAdmin()->create());
    Livewire::test(ManageRoles::class)
        ->callAction(TestAction::make('edit')->table($deskRole), [
            'name' => $deskRole->name,
            'permissions' => Permission::query()->where('name', AdminPermission::ManageOrders->value)->pluck('id')->all(),
        ])
        ->assertHasNoFormErrors();

    $this->actingAs($volunteer->fresh())->get('/admin/orders')->assertOk();
    $this->actingAs($volunteer->fresh())->get('/admin/headset-desk')->assertForbidden();
});

test('the super admin role can’t be changed or removed, and a role someone still has can’t be removed', function () {
    $superAdmin = User::factory()->superAdmin()->create();
    User::factory()->deskStaff()->create();

    $this->actingAs($superAdmin);

    Livewire::test(ManageRoles::class)
        ->assertActionHidden(TestAction::make('edit')->table(Role::findByName(StaffRole::SuperAdmin->value)))
        ->assertActionHidden(TestAction::make('delete')->table(Role::findByName(StaffRole::SuperAdmin->value)))
        ->assertActionHidden(TestAction::make('delete')->table(Role::findByName(StaffRole::HeadsetDesk->value)))
        ->assertActionVisible(TestAction::make('delete')->table(Role::findByName(StaffRole::Admin->value)));
});

test('only a super admin can make someone a super admin or change a super admin', function () {
    $peopleManager = User::factory()->withPermissions(AdminPermission::ManagePeople)->create();
    $superAdmin = User::factory()->superAdmin()->create();
    $superAdminRole = Role::findByName(StaffRole::SuperAdmin->value);

    $this->actingAs($peopleManager);

    Livewire::test(ManageUsers::class)
        ->callAction('create', ['name' => 'Kemi', 'email' => 'kemi@example.com', 'roles' => [$superAdminRole->id], 'password' => 'a-long-password-1'])
        ->assertHasFormErrors(['roles']);

    Livewire::test(ManageUsers::class)
        ->assertActionHidden(TestAction::make('edit')->table($superAdmin))
        ->assertActionHidden(TestAction::make('delete')->table($superAdmin));

    expect(User::query()->where('email', 'kemi@example.com')->exists())->toBeFalse();
});

test('organisers can add someone who only works the headset desk', function () {
    $this->actingAs(User::factory()->superAdmin()->create());

    Livewire::test(ManageUsers::class)
        ->callAction('create', [
            'name' => 'Kemi',
            'email' => 'kemi@example.com',
            'roles' => [Role::findByName(StaffRole::HeadsetDesk->value)->id],
            'password' => 'a-long-password-1',
        ])
        ->assertHasNoFormErrors();

    $kemi = User::query()->where('email', 'kemi@example.com')->firstOrFail();

    expect($kemi->can(AdminPermission::UseHeadsetDesk->value))->toBeTrue()
        ->and($kemi->can(AdminPermission::ManageOrders->value))->toBeFalse();
});

test('nobody can change their own roles or remove themselves', function () {
    $superAdmin = User::factory()->superAdmin()->create();

    $this->actingAs($superAdmin);

    Livewire::test(ManageUsers::class)
        ->assertActionHidden(TestAction::make('delete')->table($superAdmin))
        ->mountAction(TestAction::make('edit')->table($superAdmin))
        ->assertFormFieldDisabled('roles');
});

<?php

use App\Enums\AdminPermission;
use App\Enums\StaffRole;
use App\Filament\Resources\AdminInvitations\AdminInvitationResource;
use App\Filament\Resources\AdminInvitations\Pages\ManageAdminInvitations;
use App\Filament\Resources\Roles\Pages\ManageRoles;
use App\Filament\Resources\Users\Pages\ManageUsers;
use App\Mail\AdminInvitationMail;
use App\Models\AdminInvitation;
use App\Models\User;
use Filament\Actions\Testing\TestAction;
use Illuminate\Contracts\Queue\ShouldBeEncrypted;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Str;
use Livewire\Livewire;
use Spatie\Permission\Models\Role;

uses(RefreshDatabase::class);

/**
 * An invitation for Kemi to work the headset desk, with its link's secret.
 *
 * @return array{AdminInvitation, string}
 */
function invitationForKemi(?User $invitedBy = null, StaffRole $role = StaffRole::HeadsetDesk): array
{
    $invitation = AdminInvitation::factory()->create([
        'email' => 'kemi@example.com',
        'invited_by' => ($invitedBy ?? User::factory()->superAdmin()->create(['name' => 'Joseph Ajibodu']))->id,
    ]);
    $invitation->roles()->attach(Role::findByName($role->value));

    return [$invitation, $invitation->renewLink()];
}

/**
 * What the person fills in to accept, unless the test says otherwise.
 *
 * @param  array<string, string>  $overrides
 * @return array<string, string>
 */
function acceptForm(array $overrides = []): array
{
    return [
        'name' => 'Kemi Adeyemi',
        'password' => 'a-long-password-1',
        'password_confirmation' => 'a-long-password-1',
        ...$overrides,
    ];
}

/**
 * The invitation an emailed link belongs to.
 */
function invitationLinkedFrom(AdminInvitationMail $mail): ?AdminInvitation
{
    return AdminInvitation::findByToken(Str::afterLast($mail->acceptUrl, '/'));
}

function headsetDeskRoleId(): int
{
    return Role::findByName(StaffRole::HeadsetDesk->value)->id;
}

test('someone who manages people can invite a person with roles, and they’re emailed a link', function () {
    Mail::fake();
    $this->freezeSecond();
    $organiser = User::factory()->withPermissions(AdminPermission::ManagePeople)->create();

    $this->actingAs($organiser);

    Livewire::test(ManageAdminInvitations::class)
        ->callAction('invite', ['email' => 'Kemi@Example.com', 'roles' => [headsetDeskRoleId()]])
        ->assertHasNoFormErrors()
        ->assertNotified('Invitation sent to kemi@example.com');

    $invitation = AdminInvitation::query()->sole();

    expect($invitation->email)->toBe('kemi@example.com')
        ->and($invitation->invited_by)->toBe($organiser->id)
        ->and($invitation->expires_at->equalTo(now()->addDay()))->toBeTrue()
        ->and($invitation->roles->pluck('name')->all())->toBe([StaffRole::HeadsetDesk->value]);

    Mail::assertQueued(AdminInvitationMail::class, fn (AdminInvitationMail $mail): bool => $mail->hasTo('kemi@example.com')
        && $mail->invitation->is($invitation)
        && invitationLinkedFrom($mail)?->is($invitation));
});

test('the invite button is on the people page too', function () {
    Mail::fake();
    $this->actingAs(User::factory()->superAdmin()->create());

    Livewire::test(ManageUsers::class)
        ->callAction('invite', ['email' => 'kemi@example.com', 'roles' => [headsetDeskRoleId()]])
        ->assertHasNoFormErrors();

    Mail::assertQueued(AdminInvitationMail::class);
});

test('the link’s secret isn’t stored, so reading the database doesn’t give anyone a working link', function () {
    [$invitation, $token] = invitationForKemi();

    expect($invitation->fresh()->getRawOriginal('token'))->not->toBe($token);

    $this->get(route('invitations.show', $invitation->getRawOriginal('token')))->assertNotFound();
    $this->get(route('invitations.show', $token))->assertOk();
});

test('an invitation needs a valid email and at least one role', function (array $data, string $field, string $message) {
    Mail::fake();
    $this->actingAs(User::factory()->superAdmin()->create());

    Livewire::test(ManageAdminInvitations::class)
        ->callAction('invite', $data)
        ->assertHasFormErrors([$field => $message]);

    Mail::assertNothingQueued();
    expect(AdminInvitation::count())->toBe(0);
})->with([
    'no email' => [fn () => ['email' => '', 'roles' => [headsetDeskRoleId()]], 'email', 'The email address field is required.'],
    'bad email' => [fn () => ['email' => 'not-an-email', 'roles' => [headsetDeskRoleId()]], 'email', 'The email address field must be a valid email address.'],
    'no roles' => [['email' => 'kemi@example.com', 'roles' => []], 'roles', 'Choose at least one role, or they won’t be able to sign in.'],
]);

test('someone with a login or a waiting invitation can’t be invited again', function () {
    Mail::fake();
    $this->actingAs(User::factory()->superAdmin()->create());
    User::factory()->create(['email' => 'has-login@example.com']);
    AdminInvitation::factory()->create(['email' => 'waiting@example.com']);

    Livewire::test(ManageAdminInvitations::class)
        ->callAction('invite', ['email' => 'has-login@example.com', 'roles' => [headsetDeskRoleId()]])
        ->assertHasFormErrors(['email' => 'This email address already has a login. Change their roles under Settings > People instead.']);

    Livewire::test(ManageAdminInvitations::class)
        ->callAction('invite', ['email' => 'WAITING@example.com', 'roles' => [headsetDeskRoleId()]])
        ->assertHasFormErrors(['email' => 'This email address already has an invitation waiting for a reply. Send that one again instead.']);

    Mail::assertNothingQueued();
});

test('someone whose earlier invitation closed can be invited again', function () {
    Mail::fake();
    $this->actingAs(User::factory()->superAdmin()->create());
    AdminInvitation::factory()->expired()->create(['email' => 'expired@example.com']);
    AdminInvitation::factory()->declined()->create(['email' => 'declined@example.com']);

    foreach (['expired@example.com', 'declined@example.com'] as $email) {
        Livewire::test(ManageAdminInvitations::class)
            ->callAction('invite', ['email' => $email, 'roles' => [headsetDeskRoleId()]])
            ->assertHasNoFormErrors();
    }

    Mail::assertQueued(AdminInvitationMail::class, 2);
});

test('only a super admin can invite a super admin', function () {
    Mail::fake();
    $superAdminRoleId = Role::findByName(StaffRole::SuperAdmin->value)->id;

    $this->actingAs(User::factory()->withPermissions(AdminPermission::ManagePeople)->create());

    Livewire::test(ManageAdminInvitations::class)
        ->callAction('invite', ['email' => 'kemi@example.com', 'roles' => [$superAdminRoleId]])
        ->assertHasFormErrors(['roles' => 'You can’t give out that role.']);

    Mail::assertNothingQueued();

    $this->actingAs(User::factory()->superAdmin()->create());

    Livewire::test(ManageAdminInvitations::class)
        ->callAction('invite', ['email' => 'kemi@example.com', 'roles' => [$superAdminRoleId]])
        ->assertHasNoFormErrors();

    Mail::assertQueued(AdminInvitationMail::class);
});

test('the invitation page says who invited the person and what they’ll be', function () {
    [, $token] = invitationForKemi();

    $this->get(route('invitations.show', $token))
        ->assertOk()
        ->assertSeeText('Joseph Ajibodu invited you')
        ->assertSeeText('Headset desk')
        ->assertSee('kemi@example.com')
        ->assertSeeText('Accept and sign in')
        ->assertSee(route('invitations.decline-form', $token), false);
});

test('accepting makes a login with the invited roles, signs the person in and takes them into the admin', function () {
    [$invitation, $token] = invitationForKemi();

    $this->post(route('invitations.accept', $token), acceptForm())
        ->assertRedirect(route('filament.admin.home'));

    $kemi = User::query()->where('email', 'kemi@example.com')->sole();

    $this->assertAuthenticatedAs($kemi);

    expect($kemi->name)->toBe('Kemi Adeyemi')
        ->and(Hash::check('a-long-password-1', $kemi->password))->toBeTrue()
        ->and($kemi->email_verified_at)->not->toBeNull()
        ->and($kemi->getRoleNames()->all())->toBe([StaffRole::HeadsetDesk->value])
        ->and($invitation->fresh()->accepted_at)->not->toBeNull();

    $this->get('/admin/headset-desk')->assertOk();
    $this->get('/admin/orders')->assertForbidden();
});

test('accepting uses the invited email address whatever the form sends', function () {
    [, $token] = invitationForKemi();

    $this->post(route('invitations.accept', $token), acceptForm(['email' => 'attacker@example.com']));

    expect(User::query()->where('email', 'attacker@example.com')->exists())->toBeFalse()
        ->and(User::query()->where('email', 'kemi@example.com')->exists())->toBeTrue();
});

test('accepting while signed in as someone else leaves the browser signed in as the new person', function () {
    [, $token] = invitationForKemi();
    $organiser = User::factory()->superAdmin()->create();

    $this->actingAs($organiser)
        ->withSession(['password_hash_web' => 'hash-of-the-organisers-password'])
        ->post(route('invitations.accept', $token), acceptForm())
        ->assertRedirect(route('filament.admin.home'));

    $this->get('/admin/headset-desk')->assertOk();

    $this->assertAuthenticatedAs(User::query()->where('email', 'kemi@example.com')->sole());
});

test('the accept form needs a name and a confirmed password of at least 12 characters', function (array $overrides, string $field, string $message) {
    [$invitation, $token] = invitationForKemi();

    $this->post(route('invitations.accept', $token), acceptForm($overrides))
        ->assertRedirect(route('invitations.show', $token))
        ->assertSessionHasErrors([$field => $message]);

    $this->assertGuest();

    expect(User::query()->where('email', 'kemi@example.com')->exists())->toBeFalse()
        ->and($invitation->fresh()->isWaiting())->toBeTrue();
})->with([
    'no name' => [['name' => ''], 'name', 'Enter your full name.'],
    'no password' => [['password' => '', 'password_confirmation' => ''], 'password', 'Choose a password.'],
    'short password' => [['password' => 'too-short', 'password_confirmation' => 'too-short'], 'password', 'Use at least 12 characters.'],
    'passwords differ' => [['password_confirmation' => 'a-different-password'], 'password', 'The two passwords don’t match.'],
]);

test('an invitation can only be accepted once', function () {
    [, $token] = invitationForKemi();

    $this->post(route('invitations.accept', $token), acceptForm())->assertRedirect(route('filament.admin.home'));

    $this->post(route('invitations.accept', $token), acceptForm(['name' => 'Someone Else']))
        ->assertRedirect(route('invitations.show', $token));

    expect(User::query()->where('email', 'kemi@example.com')->sole()->name)->toBe('Kemi Adeyemi');

    $this->get(route('invitations.show', $token))
        ->assertOk()
        ->assertSeeText('You’ve already accepted')
        ->assertSeeText('Go to sign in')
        ->assertDontSeeText('Accept and sign in');
});

test('someone given a login by hand after being invited is told to sign in instead', function () {
    [, $token] = invitationForKemi();
    $existing = User::factory()->create(['email' => 'kemi@example.com', 'name' => 'Added By Hand']);

    $this->followingRedirects()
        ->post(route('invitations.accept', $token), acceptForm())
        ->assertOk()
        ->assertSeeText('This email address already has a login, so you can sign in with it instead.');

    $this->assertGuest();

    expect($existing->fresh()->name)->toBe('Added By Hand');
});

test('following the email’s links changes nothing, because mail scanners open links on their own', function () {
    [$invitation, $token] = invitationForKemi();

    $this->get(route('invitations.show', $token))->assertOk()->assertSeeText('Accept and sign in');
    $this->get(route('invitations.decline-form', $token))
        ->assertOk()
        ->assertSeeText('Decline this invitation?')
        ->assertSeeText('Joseph Ajibodu will see that you said no')
        ->assertSeeText('Yes, decline');

    expect($invitation->fresh()->isWaiting())->toBeTrue();
});

test('declining closes the invitation without making a login', function () {
    [$invitation, $token] = invitationForKemi();

    $this->post(route('invitations.decline', $token))->assertRedirect(route('invitations.show', $token));

    expect($invitation->fresh()->declined_at)->not->toBeNull()
        ->and(User::query()->where('email', 'kemi@example.com')->exists())->toBeFalse();

    $this->get(route('invitations.show', $token))
        ->assertOk()
        ->assertSeeText('You declined this invitation')
        ->assertDontSeeText('Accept and sign in');
});

test('a closed invitation explains itself and can’t be answered', function (Closure $close, string $heading) {
    [$invitation, $token] = invitationForKemi();
    $close($invitation);

    $this->get(route('invitations.show', $token))
        ->assertOk()
        ->assertSeeText($heading)
        ->assertSeeText('Joseph Ajibodu')
        ->assertDontSeeText('Accept and sign in');

    $this->get(route('invitations.decline-form', $token))->assertRedirect(route('invitations.show', $token));

    $this->post(route('invitations.accept', $token), acceptForm());
    $this->post(route('invitations.decline', $token));

    $this->assertGuest();

    expect(User::query()->where('email', 'kemi@example.com')->exists())->toBeFalse()
        ->and($invitation->fresh()->accepted_at)->toBeNull()
        ->and($invitation->fresh()->declined_at)->toBeNull();
})->with([
    'expired' => [fn (AdminInvitation $invitation) => $invitation->forceFill(['expires_at' => now()->subMinute()])->save(), 'This invitation has expired'],
    'cancelled' => [fn (AdminInvitation $invitation) => $invitation->forceFill(['cancelled_at' => now()])->save(), 'This invitation was cancelled'],
]);

test('a link stops working after 24 hours', function () {
    [$invitation, $token] = invitationForKemi();

    $this->travel(25)->hours();

    $this->post(route('invitations.accept', $token), acceptForm());

    expect(User::query()->where('email', 'kemi@example.com')->exists())->toBeFalse()
        ->and($invitation->fresh()->accepted_at)->toBeNull();
});

test('a link that doesn’t match any invitation is not found', function () {
    $this->get(route('invitations.show', 'not-a-real-token'))->assertNotFound();
    $this->get(route('invitations.decline-form', 'not-a-real-token'))->assertNotFound();
    $this->post(route('invitations.accept', 'not-a-real-token'), acceptForm())->assertNotFound();
    $this->post(route('invitations.decline', 'not-a-real-token'))->assertNotFound();
});

test('sending again makes a new link with a fresh expiry, and the old link stops working', function () {
    Mail::fake();
    [$invitation, $oldToken] = invitationForKemi();
    $this->actingAs(User::factory()->superAdmin()->create());

    $this->travel(30)->hours();
    $this->freezeSecond();

    Livewire::test(ManageAdminInvitations::class)
        ->callAction(TestAction::make('sendAgain')->table($invitation))
        ->assertNotified('New link sent to kemi@example.com');

    expect($invitation->fresh()->expires_at->equalTo(now()->addDay()))->toBeTrue()
        ->and($invitation->fresh()->isWaiting())->toBeTrue()
        ->and(AdminInvitation::findByToken($oldToken))->toBeNull();

    Mail::assertQueued(AdminInvitationMail::class, fn (AdminInvitationMail $mail): bool => invitationLinkedFrom($mail)?->is($invitation));
});

test('sending again is refused if that person has since got a login or a newer invitation', function (Closure $since, string $message) {
    Mail::fake();
    [$invitation] = invitationForKemi();
    $invitation->forceFill(['expires_at' => now()->subMinute()])->save();
    $this->actingAs(User::factory()->superAdmin()->create());
    $since();

    Livewire::test(ManageAdminInvitations::class)
        ->callAction(TestAction::make('sendAgain')->table($invitation))
        ->assertNotified($message);

    Mail::assertNothingQueued();
    expect($invitation->fresh()->isWaiting())->toBeFalse();
})->with([
    'a login' => [fn () => User::factory()->create(['email' => 'kemi@example.com']), 'This email address already has a login. Change their roles under Settings > People instead.'],
    'a newer invitation' => [fn () => AdminInvitation::factory()->create(['email' => 'kemi@example.com']), 'This email address already has an invitation waiting for a reply. Send that one again instead.'],
]);

test('cancelling stops the link working', function () {
    [$invitation, $token] = invitationForKemi();
    $this->actingAs(User::factory()->superAdmin()->create());

    Livewire::test(ManageAdminInvitations::class)
        ->callAction(TestAction::make('cancelInvitation')->table($invitation))
        ->assertNotified('Invitation cancelled');

    Auth::logout();
    $this->post(route('invitations.accept', $token), acceptForm());

    expect($invitation->fresh()->cancelled_at)->not->toBeNull()
        ->and(User::query()->where('email', 'kemi@example.com')->exists())->toBeFalse();
});

test('the table only offers what each invitation’s state allows', function (string $state, bool $canSendAgain, bool $canCancel) {
    $invitation = AdminInvitation::factory()->{$state}()->create();
    $invitation->roles()->attach(headsetDeskRoleId());
    $this->actingAs(User::factory()->superAdmin()->create());

    $page = Livewire::test(ManageAdminInvitations::class);

    $canSendAgain
        ? $page->assertActionVisible(TestAction::make('sendAgain')->table($invitation))
        : $page->assertActionHidden(TestAction::make('sendAgain')->table($invitation));

    $canCancel
        ? $page->assertActionVisible(TestAction::make('cancelInvitation')->table($invitation))
        : $page->assertActionHidden(TestAction::make('cancelInvitation')->table($invitation));
})->with([
    'expired' => ['expired', true, false],
    'accepted' => ['accepted', false, false],
    'declined' => ['declined', false, false],
    'cancelled' => ['cancelled', false, false],
]);

test('only a super admin can send again or cancel an invitation that would make someone a super admin', function () {
    [$invitation] = invitationForKemi(role: StaffRole::SuperAdmin);

    $this->actingAs(User::factory()->withPermissions(AdminPermission::ManagePeople)->create());

    Livewire::test(ManageAdminInvitations::class)
        ->assertActionHidden(TestAction::make('sendAgain')->table($invitation))
        ->assertActionHidden(TestAction::make('cancelInvitation')->table($invitation));

    $this->actingAs(User::factory()->superAdmin()->create());

    Livewire::test(ManageAdminInvitations::class)
        ->assertActionVisible(TestAction::make('sendAgain')->table($invitation))
        ->assertActionVisible(TestAction::make('cancelInvitation')->table($invitation));
});

test('an expired invitation whose role has since been removed can’t be sent again', function () {
    $role = Role::create(['name' => 'Short-lived role']);
    $invitation = AdminInvitation::factory()->expired()->create();
    $invitation->roles()->attach($role);
    $role->delete();

    $this->actingAs(User::factory()->superAdmin()->create());

    Livewire::test(ManageAdminInvitations::class)
        ->assertActionHidden(TestAction::make('sendAgain')->table($invitation));
});

test('a role promised in a waiting invitation can’t be removed until the invitation closes', function () {
    $role = Role::create(['name' => 'Volunteers']);
    $invitation = AdminInvitation::factory()->create();
    $invitation->roles()->attach($role);

    $this->actingAs(User::factory()->superAdmin()->create());

    Livewire::test(ManageRoles::class)->assertActionHidden(TestAction::make('delete')->table($role));

    $invitation->forceFill(['cancelled_at' => now()])->save();

    Livewire::test(ManageRoles::class)->assertActionVisible(TestAction::make('delete')->table($role));
});

test('the menu shows how many invitations are waiting for a reply', function () {
    AdminInvitation::factory()->count(2)->create();
    AdminInvitation::factory()->expired()->create();
    AdminInvitation::factory()->accepted()->create();

    expect(AdminInvitationResource::getNavigationBadge())->toBe('2');
});

test('the invitation email has both links, the roles and who sent it, in the official look', function () {
    [$invitation, $token] = invitationForKemi();
    $invitation->forceFill(['expires_at' => '2026-11-06 08:15:00'])->save();

    $acceptUrl = route('invitations.show', $token);
    $declineUrl = route('invitations.decline-form', $token);
    $mail = new AdminInvitationMail($invitation->fresh(), $acceptUrl, $declineUrl);

    $mail->assertHasSubject('You’re invited to the Let\'s Run Ibadan admin');
    $mail->assertFrom(config('mail.from.address'), config('lri.site.name'));

    $mail->assertSeeInOrderInHtml(['Joseph Ajibodu', 'Headset desk', 'kemi@example.com', 'Friday 6 November at 9:15 AM WAT', 'Accept invitation', 'Decline this invitation']);
    $mail->assertSeeInHtml($acceptUrl, false);
    $mail->assertSeeInHtml($declineUrl, false);
    $mail->assertSeeInHtml('images/lri-logo-email.png', false);
    $mail->assertDontSeeInHtml('images/lri-logo-on-dark.png', false);
    $mail->assertDontSeeInHtml('#0B0A10;color', false);
    $mail->assertDontSeeInHtml('See you on the run');

    $mail->assertSeeInText('Accept invitation: '.$acceptUrl);
    $mail->assertSeeInText('Decline this invitation: '.$declineUrl);
    $mail->assertSeeInText('Joseph Ajibodu');
});

test('the invitation email keeps the link’s secret out of the queue in plain text', function () {
    expect(new AdminInvitationMail(new AdminInvitation, 'accept-link', 'decline-link'))->toBeInstanceOf(ShouldBeEncrypted::class);
});

test('the invitation email and page escape names', function () {
    [$invitation, $token] = invitationForKemi(User::factory()->superAdmin()->create(['name' => '<script>alert(1)</script>']));

    $html = (new AdminInvitationMail($invitation->fresh(), 'accept-link', 'decline-link'))->render();

    expect($html)->not->toContain('<script>alert(1)</script>')
        ->toContain('&lt;script&gt;');

    $this->get(route('invitations.show', $token))
        ->assertOk()
        ->assertDontSee('<script>alert(1)</script>', false);
});

test('an invitation still works if the person who sent it has been removed', function () {
    $organiser = User::factory()->superAdmin()->create();
    [$invitation, $token] = invitationForKemi($organiser);
    $organiser->delete();

    $this->get(route('invitations.show', $token))
        ->assertOk()
        ->assertSeeText('The Let\'s Run Ibadan team invited you');

    (new AdminInvitationMail($invitation->fresh(), 'accept-link', 'decline-link'))->assertSeeInText('The Let\'s Run Ibadan team has invited you');
});

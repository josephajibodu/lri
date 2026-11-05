<?php

use App\Enums\StaffRole;
use App\Models\User;
use Database\Seeders\AdminUserSeeder;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;

uses(RefreshDatabase::class);

test('the seeder makes the default admin a super admin who can use the admin', function () {
    config()->set('lri.default_admin.password', 'a-chosen-password');

    $this->artisan('db:seed', ['--class' => AdminUserSeeder::class])
        ->expectsOutputToContain('with the password in DEFAULT_ADMIN_PASSWORD')
        ->doesntExpectOutputToContain('a-chosen-password')
        ->assertSuccessful();

    $admin = User::query()->where('email', 'joseph@gmail.com')->sole();

    expect(Hash::check('a-chosen-password', $admin->password))->toBeTrue()
        ->and($admin->hasRole(StaffRole::SuperAdmin->value))->toBeTrue();

    $this->actingAs($admin)->get('/admin/users')->assertOk();
});

test('with no password set, the seeder makes a random one and shows it once', function () {
    config()->set('lri.default_admin.password', null);

    $this->artisan('db:seed', ['--class' => AdminUserSeeder::class])
        ->expectsOutputToContain('Save this password now')
        ->assertSuccessful();

    expect(Hash::check('', User::query()->where('email', 'joseph@gmail.com')->sole()->password))->toBeFalse();

    $this->artisan('db:seed', ['--class' => AdminUserSeeder::class])
        ->doesntExpectOutputToContain('Save this password now')
        ->assertSuccessful();
});

test('running the seeder again keeps the password and adds no second login', function () {
    config()->set('lri.default_admin.password', 'the-first-password');
    $this->seed(AdminUserSeeder::class);

    config()->set('lri.default_admin.password', 'a-different-password');
    $this->seed(AdminUserSeeder::class);

    $admin = User::query()->where('email', 'joseph@gmail.com')->sole();

    expect(Hash::check('the-first-password', $admin->password))->toBeTrue();
});

test('running the seeder again gives a login that lost its super admin role the role back', function () {
    $this->seed(AdminUserSeeder::class);
    $admin = User::query()->where('email', 'joseph@gmail.com')->sole();
    $admin->syncRoles([]);

    $this->seed(AdminUserSeeder::class);

    expect($admin->fresh()->hasRole(StaffRole::SuperAdmin->value))->toBeTrue();
});

test('the default admin’s address can be changed from the environment', function () {
    config()->set('lri.default_admin.email', 'organiser@example.com');

    $this->seed(AdminUserSeeder::class);

    expect(User::query()->where('email', 'organiser@example.com')->sole()->hasRole(StaffRole::SuperAdmin->value))->toBeTrue();
});

test('the database seeder works without factories, which production installs can’t use', function () {
    Factory::guessFactoryNamesUsing(fn () => throw new RuntimeException('Seeders must not use factories.'));

    try {
        $this->seed(DatabaseSeeder::class);
    } finally {
        Factory::flushState();
    }

    expect(User::query()->pluck('email')->all())->toBe(['joseph@gmail.com']);
    $this->assertDatabaseHas('ticket_types', ['slug' => 'disco-run-standard']);
});

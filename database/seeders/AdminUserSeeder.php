<?php

namespace Database\Seeders;

use App\Enums\StaffRole;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Str;
use Spatie\Permission\Models\Role;

/**
 * The first login, a super admin, so someone can get into the admin and invite everyone else. Safe to run again in
 * production: it never changes the password of a login that already exists, and it doesn't use factories, because
 * production installs leave out the package they need.
 */
class AdminUserSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $email = config('lri.default_admin.email');
        $chosenPassword = config('lri.default_admin.password');
        $password = filled($chosenPassword) ? $chosenPassword : Str::password(24, symbols: false);

        $admin = User::query()->firstOrCreate(['email' => $email], ['name' => 'Joseph', 'password' => $password]);
        $admin->assignRole(Role::findOrCreate(StaffRole::SuperAdmin->value, 'web'));

        if (! $admin->wasRecentlyCreated) {
            $this->command?->info("{$email} already has a login, so its password was left alone.");

            return;
        }

        if (filled($chosenPassword)) {
            $this->command?->info("Created {$email} as a super admin, with the password in DEFAULT_ADMIN_PASSWORD.");

            return;
        }

        $this->command?->warn("Created {$email} as a super admin. Save this password now, it won’t be shown again: {$password}");
    }
}

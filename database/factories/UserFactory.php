<?php

namespace Database\Factories;

use App\Enums\AdminPermission;
use App\Enums\StaffRole;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Spatie\Permission\Models\Role;

/**
 * @extends Factory<User>
 */
class UserFactory extends Factory
{
    /**
     * The current password being used by the factory.
     */
    protected static ?string $password;

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'name' => fake()->name(),
            'email' => fake()->unique()->safeEmail(),
            'email_verified_at' => now(),
            'password' => static::$password ??= Hash::make('password'),
            'remember_token' => Str::random(10),
        ];
    }

    /**
     * Someone who sees and does everything in the admin.
     */
    public function superAdmin(): static
    {
        return $this->withRole(StaffRole::SuperAdmin);
    }

    /**
     * Someone who only works the headset desk.
     */
    public function deskStaff(): static
    {
        return $this->withRole(StaffRole::HeadsetDesk);
    }

    /**
     * Someone with one of the roles the site starts with.
     */
    public function withRole(StaffRole $role): static
    {
        return $this->afterCreating(fn (User $user) => $user->assignRole($role->value));
    }

    /**
     * Someone whose own role allows only the given parts of the admin.
     */
    public function withPermissions(AdminPermission ...$permissions): static
    {
        return $this->afterCreating(function (User $user) use ($permissions): void {
            $role = Role::create(['name' => 'Only '.implode(', ', array_map(fn (AdminPermission $permission): string => $permission->value, $permissions)).' '.$user->id]);
            $role->givePermissionTo(array_map(fn (AdminPermission $permission): string => $permission->value, $permissions));
            $user->assignRole($role);
        });
    }

    /**
     * Indicate that the model's email address should be unverified.
     */
    public function unverified(): static
    {
        return $this->state(fn (array $attributes) => [
            'email_verified_at' => null,
        ]);
    }
}

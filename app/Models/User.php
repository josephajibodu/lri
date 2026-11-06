<?php

namespace App\Models;

use App\Enums\StaffRole;
use App\Models\Concerns\RecordsAdminActivity;
use App\Support\AdminActivity;
use Database\Factories\UserFactory;
use Filament\Models\Contracts\FilamentUser;
use Filament\Panel;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Spatie\Permission\Traits\HasRoles;

#[Fillable(['name', 'email', 'password'])]
#[Hidden(['password', 'remember_token'])]
/**
 * Someone who helps run the site through the admin. Their roles decide which parts of the admin they see.
 */
class User extends Authenticatable implements FilamentUser
{
    /** @use HasFactory<UserFactory> */
    use HasFactory, HasRoles, Notifiable, RecordsAdminActivity;

    /**
     * A password is never recorded, so the log only says that it changed.
     */
    protected static function booted(): void
    {
        static::updated(function (User $user): void {
            if ($user->wasChanged('password')) {
                AdminActivity::record("Changed the password of {$user->activityLabel()}", $user);
            }
        });
    }

    public function activityLabel(): string
    {
        return "admin {$this->name}";
    }

    /**
     * @return list<string>
     */
    protected function activityAttributes(): array
    {
        return ['name', 'email'];
    }

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
        ];
    }

    /**
     * Whether this person can see and do everything in the admin.
     */
    public function isSuperAdmin(): bool
    {
        return $this->hasRole(StaffRole::SuperAdmin->value);
    }

    /**
     * Anyone with a role may sign in; what they see once inside depends on their roles.
     */
    public function canAccessPanel(Panel $panel): bool
    {
        return $this->roles()->exists();
    }
}

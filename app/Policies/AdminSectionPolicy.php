<?php

namespace App\Policies;

use App\Enums\AdminPermission;
use App\Models\User;
use Illuminate\Database\Eloquent\Model;

/**
 * Access to one part of the admin: someone whose roles include that part's permission can see and change its
 * records; everyone else can't open it at all. Super admins pass every check before it gets here.
 */
abstract class AdminSectionPolicy
{
    /**
     * The permission that opens this part of the admin.
     */
    abstract protected function permission(): AdminPermission;

    protected function allowed(User $user): bool
    {
        return $user->can($this->permission()->value);
    }

    public function viewAny(User $user): bool
    {
        return $this->allowed($user);
    }

    public function view(User $user, Model $record): bool
    {
        return $this->allowed($user);
    }

    public function create(User $user): bool
    {
        return $this->allowed($user);
    }

    public function update(User $user, Model $record): bool
    {
        return $this->allowed($user);
    }

    public function delete(User $user, Model $record): bool
    {
        return $this->allowed($user);
    }

    public function deleteAny(User $user): bool
    {
        return $this->allowed($user);
    }

    public function reorder(User $user): bool
    {
        return $this->allowed($user);
    }
}

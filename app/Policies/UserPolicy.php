<?php

namespace App\Policies;

use App\Enums\AdminPermission;
use App\Models\User;
use Illuminate\Database\Eloquent\Model;

/**
 * The people who can sign in. Only a super admin can change or remove another super admin, and nobody can remove
 * themselves.
 */
class UserPolicy extends AdminSectionPolicy
{
    protected function permission(): AdminPermission
    {
        return AdminPermission::ManagePeople;
    }

    /**
     * @param  User  $record
     */
    public function update(User $user, Model $record): bool
    {
        return $this->allowed($user) && ($user->isSuperAdmin() || ! $record->isSuperAdmin());
    }

    /**
     * @param  User  $record
     */
    public function delete(User $user, Model $record): bool
    {
        return $this->update($user, $record) && $record->isNot($user);
    }

    public function deleteAny(User $user): bool
    {
        return false;
    }
}

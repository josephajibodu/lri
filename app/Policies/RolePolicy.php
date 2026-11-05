<?php

namespace App\Policies;

use App\Enums\AdminPermission;
use App\Enums\StaffRole;
use App\Models\AdminInvitation;
use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Spatie\Permission\Models\Role;

/**
 * The roles people can be given. The super admin role always has everything, so it can't be changed or removed,
 * and a role still given to someone, or promised in a waiting invitation, can't be removed.
 */
class RolePolicy extends AdminSectionPolicy
{
    protected function permission(): AdminPermission
    {
        return AdminPermission::ManagePeople;
    }

    /**
     * @param  Role  $record
     */
    public function update(User $user, Model $record): bool
    {
        return $record->name !== StaffRole::SuperAdmin->value && $this->allowed($user);
    }

    /**
     * @param  Role  $record
     */
    public function delete(User $user, Model $record): bool
    {
        return $this->update($user, $record)
            && $record->users()->doesntExist()
            && AdminInvitation::query()->waiting()->whereRelation('roles', 'roles.id', $record->getKey())->doesntExist();
    }

    public function deleteAny(User $user): bool
    {
        return false;
    }
}

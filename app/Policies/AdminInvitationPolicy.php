<?php

namespace App\Policies;

use App\Enums\AdminPermission;
use App\Models\AdminInvitation;
use App\Models\User;
use Illuminate\Database\Eloquent\Model;

/**
 * Invitations to the admin are part of People and roles. As with logins, only a super admin can touch an invitation
 * that would make someone a super admin.
 */
class AdminInvitationPolicy extends AdminSectionPolicy
{
    protected function permission(): AdminPermission
    {
        return AdminPermission::ManagePeople;
    }

    /**
     * @param  AdminInvitation  $record
     */
    public function update(User $user, Model $record): bool
    {
        return $this->allowed($user) && ($user->isSuperAdmin() || ! $record->grantsSuperAdmin());
    }

    /**
     * @param  AdminInvitation  $record
     */
    public function delete(User $user, Model $record): bool
    {
        return $this->update($user, $record);
    }

    public function deleteAny(User $user): bool
    {
        return false;
    }
}

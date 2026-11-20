<?php

namespace App\Policies;

use App\Enums\AdminPermission;
use App\Models\TrackedLink;
use App\Models\User;
use Illuminate\Database\Eloquent\Model;

/**
 * Tracked links: anyone with this section can make and edit them. One can only be deleted before anyone has tapped
 * it, because after that it may be printed or posted somewhere, and its visits are credited to it.
 */
class TrackedLinkPolicy extends AdminSectionPolicy
{
    protected function permission(): AdminPermission
    {
        return AdminPermission::ManageLinks;
    }

    /**
     * @param  TrackedLink  $record
     */
    public function delete(User $user, Model $record): bool
    {
        return $this->allowed($user) && $record->clicks === 0;
    }

    public function deleteAny(User $user): bool
    {
        return false;
    }
}

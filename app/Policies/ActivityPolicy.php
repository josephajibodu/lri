<?php

namespace App\Policies;

use App\Models\Activity;
use App\Models\User;

/**
 * Only super admins can read the activity log. It isn't one of the permissions a role can be given, so it can't be
 * handed to anyone else by ticking a box. Nobody can add to it, change it or remove from it here, so that it can be
 * trusted, including about the super admins themselves.
 */
class ActivityPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->isSuperAdmin();
    }

    public function view(User $user, Activity $activity): bool
    {
        return $user->isSuperAdmin();
    }

    public function create(User $user): bool
    {
        return false;
    }

    public function update(User $user, Activity $activity): bool
    {
        return false;
    }

    public function delete(User $user, Activity $activity): bool
    {
        return false;
    }

    public function deleteAny(User $user): bool
    {
        return false;
    }
}

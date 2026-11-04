<?php

namespace App\Policies;

use App\Enums\AdminPermission;

/**
 * Barracks permit sign-ups.
 */
class BarracksRegistrationPolicy extends AdminSectionPolicy
{
    protected function permission(): AdminPermission
    {
        return AdminPermission::ManageBarracksRegistrations;
    }
}

<?php

namespace App\Policies;

use App\Enums\AdminPermission;

/**
 * Birthdays members have shared.
 */
class BirthdayPolicy extends AdminSectionPolicy
{
    protected function permission(): AdminPermission
    {
        return AdminPermission::ManageBirthdays;
    }
}

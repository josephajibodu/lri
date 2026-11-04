<?php

namespace App\Policies;

use App\Enums\AdminPermission;

/**
 * What was sent to the email tool.
 */
class EmailToolSendPolicy extends AdminSectionPolicy
{
    protected function permission(): AdminPermission
    {
        return AdminPermission::ManageEmailTool;
    }
}

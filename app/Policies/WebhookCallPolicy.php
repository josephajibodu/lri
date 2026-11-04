<?php

namespace App\Policies;

use App\Enums\AdminPermission;

/**
 * Notifications from payment providers.
 */
class WebhookCallPolicy extends AdminSectionPolicy
{
    protected function permission(): AdminPermission
    {
        return AdminPermission::ManageWebhooks;
    }
}

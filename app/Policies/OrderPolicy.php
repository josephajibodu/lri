<?php

namespace App\Policies;

use App\Enums\AdminPermission;

/**
 * Orders, with each buyer's details, payments and journey.
 */
class OrderPolicy extends AdminSectionPolicy
{
    protected function permission(): AdminPermission
    {
        return AdminPermission::ManageOrders;
    }
}

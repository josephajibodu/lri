<?php

namespace App\Policies;

use App\Enums\AdminPermission;

/**
 * Ticket types: prices, limits and when sales open.
 */
class TicketTypePolicy extends AdminSectionPolicy
{
    protected function permission(): AdminPermission
    {
        return AdminPermission::ManageTicketTypes;
    }
}

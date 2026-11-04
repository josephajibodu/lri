<?php

namespace App\Policies;

use App\Enums\AdminPermission;

/**
 * The ways to pay offered at checkout.
 */
class PaymentChannelPolicy extends AdminSectionPolicy
{
    protected function permission(): AdminPermission
    {
        return AdminPermission::ManagePaymentMethods;
    }
}

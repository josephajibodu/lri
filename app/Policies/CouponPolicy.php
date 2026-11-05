<?php

namespace App\Policies;

use App\Enums\AdminPermission;
use App\Models\User;
use Illuminate\Database\Eloquent\Model;

/**
 * Coupons: anyone with this section can create them and switch them off or on. They are never deleted, because
 * orders point at them and show their code.
 */
class CouponPolicy extends AdminSectionPolicy
{
    protected function permission(): AdminPermission
    {
        return AdminPermission::ManageCoupons;
    }

    public function delete(User $user, Model $record): bool
    {
        return false;
    }

    public function deleteAny(User $user): bool
    {
        return false;
    }
}

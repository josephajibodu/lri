<?php

namespace App\Enums;

use Filament\Support\Contracts\HasLabel;

/**
 * How a coupon takes money off an order.
 */
enum CouponDiscountType: string implements HasLabel
{
    case Percentage = 'percentage';
    case FixedAmount = 'fixed_amount';

    public function getLabel(): string
    {
        return match ($this) {
            self::Percentage => 'Percentage off',
            self::FixedAmount => 'Naira off',
        };
    }
}

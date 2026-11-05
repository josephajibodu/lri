<?php

namespace App\Enums;

use Filament\Support\Contracts\HasColor;
use Filament\Support\Contracts\HasLabel;

/**
 * Where a coupon stands right now.
 */
enum CouponStatus: string implements HasColor, HasLabel
{
    case Active = 'active';
    case Scheduled = 'scheduled';
    case UsedUp = 'used_up';
    case Ended = 'ended';
    case Disabled = 'disabled';

    public function getLabel(): string
    {
        return match ($this) {
            self::Active => 'Active',
            self::Scheduled => 'Not started',
            self::UsedUp => 'Used up',
            self::Ended => 'Ended',
            self::Disabled => 'Disabled',
        };
    }

    public function getColor(): string
    {
        return match ($this) {
            self::Active => 'success',
            self::Scheduled => 'info',
            self::UsedUp => 'warning',
            self::Ended, self::Disabled => 'gray',
        };
    }
}

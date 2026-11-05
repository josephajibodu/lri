<?php

namespace App\Enums;

use Filament\Support\Contracts\HasColor;
use Filament\Support\Contracts\HasLabel;

/**
 * Where a ticket order stands. A failed payment attempt does not fail the order, since the buyer can try again.
 */
enum OrderStatus: string implements HasColor, HasLabel
{
    case Pending = 'pending';
    case Paid = 'paid';
    case Abandoned = 'abandoned';
    case Refunded = 'refunded';
    case Disputed = 'disputed';

    /**
     * The status as organisers read it.
     */
    public function label(): string
    {
        return match ($this) {
            self::Pending => 'Waiting for payment',
            self::Paid => 'Paid',
            self::Abandoned => 'Abandoned',
            self::Refunded => 'Refunded',
            self::Disputed => 'Payment disputed',
        };
    }

    public function getLabel(): string
    {
        return $this->label();
    }

    public function getColor(): string
    {
        return match ($this) {
            self::Pending => 'warning',
            self::Paid => 'success',
            self::Abandoned => 'gray',
            self::Refunded, self::Disputed => 'danger',
        };
    }
}

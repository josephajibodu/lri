<?php

namespace App\Enums;

use Filament\Support\Contracts\HasColor;
use Filament\Support\Contracts\HasLabel;

/**
 * Where a ticket order stands. A failed payment attempt does not fail the order, since the buyer can try again.
 * A test payment is a real payment an organiser made to try out checkout: it stays on record, but its tickets don't
 * work, don't count against any limit and aren't in the sales figures.
 */
enum OrderStatus: string implements HasColor, HasLabel
{
    case Pending = 'pending';
    case Paid = 'paid';
    case Abandoned = 'abandoned';
    case Refunded = 'refunded';
    case Disputed = 'disputed';
    case TestPayment = 'test';

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
            self::TestPayment => 'Test payment',
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
            self::Abandoned, self::TestPayment => 'gray',
            self::Refunded, self::Disputed => 'danger',
        };
    }
}

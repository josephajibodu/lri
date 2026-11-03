<?php

namespace App\Enums;

/**
 * Where a ticket order stands. A failed payment attempt does not fail the order, since the buyer can try again.
 */
enum OrderStatus: string
{
    case Pending = 'pending';
    case Paid = 'paid';
    case Abandoned = 'abandoned';
    case Refunded = 'refunded';

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
        };
    }
}

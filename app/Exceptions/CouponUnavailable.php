<?php

namespace App\Exceptions;

use RuntimeException;

/**
 * A coupon can't be used on this order. The message is for the buyer.
 */
class CouponUnavailable extends RuntimeException
{
    public static function notFound(): self
    {
        return new self('We can’t find that coupon. Check the spelling and try again.');
    }

    public static function ended(): self
    {
        return new self('This coupon has ended.');
    }

    public static function notStarted(): self
    {
        return new self('This coupon isn’t active yet.');
    }

    public static function usedUp(): self
    {
        return new self('This coupon has been fully used.');
    }

    public static function wrongTicket(): self
    {
        return new self('This coupon doesn’t work on this ticket.');
    }
}

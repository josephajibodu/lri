<?php

namespace App\Exceptions;

use RuntimeException;
use Throwable;

/**
 * A checkout couldn't be opened for an order. The message is for the buyer.
 */
class CheckoutUnavailable extends RuntimeException
{
    /**
     * Why, for the record of the buyer's journey.
     */
    public string $reason = 'provider_error';

    /**
     * The order's coupon stopped working while it wasn't held, so it went back to full price for the buyer to look at.
     */
    public static function couponEnded(string $code, string $newTotal): self
    {
        $exception = new self("Coupon {$code} has ended, so your order is back to full price: {$newTotal}. Choose how you’d like to pay to carry on.");
        $exception->reason = 'coupon_ended';

        return $exception;
    }

    public static function alreadyPaid(): self
    {
        return new self('This order is already paid for.');
    }

    public static function methodUnavailable(): self
    {
        return new self('That way to pay isn’t available right now. Choose another one.');
    }

    public static function providerDown(Throwable $previous): self
    {
        return new self('We couldn’t open the payment page just now. Try again, or choose another way to pay.', previous: $previous);
    }
}

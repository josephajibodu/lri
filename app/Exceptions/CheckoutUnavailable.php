<?php

namespace App\Exceptions;

use RuntimeException;
use Throwable;

/**
 * A checkout couldn't be opened for an order. The message is for the buyer.
 */
class CheckoutUnavailable extends RuntimeException
{
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

<?php

namespace App\Exceptions;

use RuntimeException;

/**
 * The tickets asked for can't be held: sales are closed, or there aren't enough left. The message is for the buyer.
 */
class TicketsUnavailable extends RuntimeException
{
    public static function notOnSale(): self
    {
        return new self('Tickets aren’t on sale right now.');
    }

    public static function tooManyUnpaid(): self
    {
        return new self('There are already unpaid tickets waiting for this email or connection. Pay for those first, or try again once their hold runs out.');
    }

    public static function notEnough(?int $ticketsLeft): self
    {
        return new self(match ($ticketsLeft) {
            0 => 'Sorry, tickets just sold out.',
            1 => 'Only 1 ticket is left. Lower the number of tickets and try again.',
            default => "Only {$ticketsLeft} tickets are left. Lower the number of tickets and try again.",
        });
    }
}

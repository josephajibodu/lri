<?php

namespace App\Exceptions;

use App\Models\Ticket;
use RuntimeException;

/**
 * A ticket can't be named or used to collect a headset right now. The message is shown to whoever tried.
 */
class TicketNotUsable extends RuntimeException
{
    public static function notPaid(): self
    {
        return new self('This ticket’s order isn’t paid, or was refunded, so it can’t be used for a headset.');
    }

    public static function alreadyCollected(Ticket $ticket): self
    {
        $when = $ticket->checked_in_at?->timezone('Africa/Lagos')->format('j M, g:i A');

        return new self('This ticket’s headset was already collected'.($when ? " at {$when}" : '').'.');
    }

    public static function notNamed(): self
    {
        return new self('Fill in this person’s name and email before handing over the headset.');
    }

    public static function alreadyNamed(): self
    {
        return new self('This ticket already has someone’s name on it. Ask the person who bought it to change it.');
    }
}

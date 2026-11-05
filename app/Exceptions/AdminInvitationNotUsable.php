<?php

namespace App\Exceptions;

use RuntimeException;

/**
 * An invitation to the admin can't be sent or answered right now. The message is shown to whoever tried.
 */
class AdminInvitationNotUsable extends RuntimeException
{
    public static function alreadyHasLogin(): self
    {
        return new self('This email address already has a login. Change their roles under Settings > People instead.');
    }

    public static function alreadyInvited(): self
    {
        return new self('This email address already has an invitation waiting for a reply. Send that one again instead.');
    }

    public static function canSignInInstead(): self
    {
        return new self('This email address already has a login, so you can sign in with it instead.');
    }

    public static function noLongerOpen(): self
    {
        return new self('This invitation can’t be used any more. Ask whoever invited you to send a new one.');
    }
}

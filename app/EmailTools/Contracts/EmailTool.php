<?php

namespace App\EmailTools\Contracts;

use App\EmailTools\Exceptions\EmailToolException;
use App\Enums\EmailToolEvent;
use App\Models\Order;

/**
 * What every email tool does for the site, in our own terms. Adding a tool means writing one of these;
 * the follow-up emails themselves are set up in the tool.
 */
interface EmailTool
{
    /**
     * The tool's key, as the config and stored sends name it.
     */
    public function name(): string;

    /**
     * Set up anything the tool needs before it can take our data, leaving what's already there alone.
     *
     * @return list<string> What was set up just now, for organisers to read
     *
     * @throws EmailToolException
     */
    public function prepare(): array;

    /**
     * Tell the tool the news about an order's buyer. Sending the same news twice must be harmless, because a retry can do that.
     * Only "paid" news may subscribe someone straight away: on an unpaid order, nothing shows the email belongs to whoever
     * typed it, so a new contact must confirm first. Someone who unsubscribed must never be signed back up.
     *
     * @throws EmailToolException
     */
    public function send(EmailToolEvent $event, Order $order): void;
}

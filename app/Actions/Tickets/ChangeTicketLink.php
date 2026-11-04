<?php

namespace App\Actions\Tickets;

use App\Exceptions\TicketNotUsable;
use App\Models\Ticket;
use Illuminate\Support\Facades\DB;

/**
 * The buyer's controls over a ticket's link and code, from their receipt. None of them work once the ticket's
 * headset has been collected, and the person who received a ticket has no way to use them.
 */
class ChangeTicketLink
{
    /**
     * Note that the buyer sent a blank ticket's link to someone, so the receipt shows "Link sent".
     *
     * @throws TicketNotUsable
     */
    public function share(Ticket $ticket): Ticket
    {
        return $this->change($ticket, function (Ticket $ticket): array {
            if ($ticket->isNamed()) {
                throw TicketNotUsable::alreadyNamed();
            }

            return ['link_shared_at' => $ticket->link_shared_at ?? now()];
        });
    }

    /**
     * Stop a sent link working before anyone fills it in. The ticket gets a new link, ready to send again.
     *
     * @throws TicketNotUsable
     */
    public function cancel(Ticket $ticket): Ticket
    {
        return $this->change($ticket, function (Ticket $ticket): array {
            if ($ticket->isNamed()) {
                throw TicketNotUsable::alreadyNamed();
            }

            return ['claim_token' => Ticket::newClaimToken(), 'link_shared_at' => null];
        });
    }

    /**
     * Give the ticket a new gate code and a new link, and take the name off it. The old QR code, link and any
     * screenshots of them stop working; the buyer names the ticket again or sends the new link.
     *
     * @throws TicketNotUsable
     */
    public function reissue(Ticket $ticket): Ticket
    {
        return $this->change($ticket, fn (): array => [
            'code' => Ticket::newCode(),
            'claim_token' => Ticket::newClaimToken(),
            'link_shared_at' => null,
            'holder_name' => null,
            'holder_email' => null,
        ]);
    }

    /**
     * Lock the ticket, check it can still change, and save what the change returns.
     *
     * @param  callable(Ticket): array<string, mixed>  $changes
     *
     * @throws TicketNotUsable
     */
    private function change(Ticket $ticket, callable $changes): Ticket
    {
        return DB::transaction(function () use ($ticket, $changes): Ticket {
            $locked = Ticket::query()->with('order')->lockForUpdate()->findOrFail($ticket->id);

            if (! $locked->isValid()) {
                throw TicketNotUsable::notPaid();
            }

            if ($locked->isCollected()) {
                throw TicketNotUsable::alreadyCollected($locked);
            }

            $locked->forceFill($changes($locked))->save();

            return $locked;
        });
    }
}

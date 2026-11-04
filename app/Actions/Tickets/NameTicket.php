<?php

namespace App\Actions\Tickets;

use App\Exceptions\TicketNotUsable;
use App\Mail\TicketMail;
use App\Models\Ticket;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;

/**
 * Put someone's name and email on a ticket, or fix a typo in them, and email that person their ticket. The buyer,
 * the holder (through the ticket's own link) and the headset desk all name tickets this way. The buyer's own
 * ticket isn't emailed again, since they already have everything. A ticket's name is fixed once its headset has
 * been collected.
 */
class NameTicket
{
    /**
     * @param  bool  $onlyIfBlank  Refuse if the ticket already has a name, so a shared link can't take a ticket over.
     *
     * @throws TicketNotUsable
     */
    public function handle(Ticket $ticket, string $name, string $email, bool $onlyIfBlank = false): Ticket
    {
        DB::transaction(function () use ($ticket, $name, $email, $onlyIfBlank): void {
            $locked = Ticket::query()->with('order')->lockForUpdate()->findOrFail($ticket->id);

            if (! $locked->isValid()) {
                throw TicketNotUsable::notPaid();
            }

            if ($locked->isCollected()) {
                throw TicketNotUsable::alreadyCollected($locked);
            }

            if ($onlyIfBlank && $locked->isNamed()) {
                throw TicketNotUsable::alreadyNamed();
            }

            $locked->forceFill(['holder_name' => $name, 'holder_email' => $email, 'link_shared_at' => null])->save();
        });

        $ticket->refresh()->load(['order', 'ticketType']);

        if (! $ticket->isBuyers()) {
            Mail::to($ticket->holder_email, $ticket->holder_name)->queue(new TicketMail($ticket));
        }

        return $ticket;
    }
}

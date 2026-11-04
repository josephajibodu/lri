<?php

namespace App\Actions\Tickets;

use App\Exceptions\TicketNotUsable;
use App\Models\Ticket;
use App\Models\User;
use Illuminate\Support\Facades\DB;

/**
 * Record that a ticket's headset has been handed over. Only a paid, named ticket gets one, and only once. The
 * ticket row is locked, so two desk staff can't hand out a headset for the same ticket at the same moment.
 */
class HandOverHeadset
{
    /**
     * @throws TicketNotUsable
     */
    public function handle(Ticket $ticket, User $staff): Ticket
    {
        return DB::transaction(function () use ($ticket, $staff): Ticket {
            $locked = Ticket::query()->with('order')->lockForUpdate()->findOrFail($ticket->id);

            if (! $locked->isValid()) {
                throw TicketNotUsable::notPaid();
            }

            if ($locked->isCollected()) {
                throw TicketNotUsable::alreadyCollected($locked);
            }

            if (! $locked->isNamed()) {
                throw TicketNotUsable::notNamed();
            }

            $locked->update(['checked_in_at' => now(), 'checked_in_by' => $staff->id]);

            return $locked;
        });
    }
}

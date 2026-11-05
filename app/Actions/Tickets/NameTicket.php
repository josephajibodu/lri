<?php

namespace App\Actions\Tickets;

use App\Exceptions\TicketNotUsable;
use App\Mail\TicketMail;
use App\Models\Ticket;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Str;

/**
 * Put someone's name and email on a ticket, or fix a typo in them, and email that person their ticket. The buyer,
 * the holder (through the ticket's own link) and the headset desk all name tickets this way. The buyer's own
 * ticket isn't emailed again, since they already have everything. A ticket that goes to a different email address gets
 * a new code and link, so whoever had it before can't use it or see who has it now. A ticket's name is fixed once its
 * headset has been collected.
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
        $changedHands = DB::transaction(function () use ($ticket, $name, $email, $onlyIfBlank): bool {
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

            $changedHands = filled($locked->holder_email) && Str::lower($locked->holder_email) !== Str::lower($email);

            $locked->forceFill([
                'holder_name' => $name,
                'holder_email' => $email,
                'link_shared_at' => null,
                ...($changedHands ? ['code' => Ticket::newCode(), 'claim_token' => Ticket::newClaimToken()] : []),
            ])->save();

            return $changedHands;
        });

        $ticket->refresh()->load(['order', 'ticketType']);

        if ($changedHands || ! $ticket->isBuyers()) {
            Mail::to($ticket->holder_email, $ticket->holder_name)->queue(new TicketMail($ticket));
        }

        return $ticket;
    }
}

<?php

namespace App\Http\Controllers;

use App\Actions\Tickets\NameTicket;
use App\Enums\OrderStatus;
use App\Exceptions\TicketNotUsable;
use App\Http\Requests\NameTicketRequest;
use App\Models\Order;
use App\Models\Ticket;
use Illuminate\Http\RedirectResponse;
use Illuminate\View\View;

/**
 * Putting a name on each ticket: by the buyer from their receipt, or by the holder from the ticket's own link.
 */
class TicketController extends Controller
{
    /**
     * One ticket at its own private link. A blank ticket asks its holder for their name and email; a named one
     * shows the ticket.
     */
    public function show(Ticket $ticket): View
    {
        $ticket->load(['order', 'ticketType']);

        abort_unless($ticket->order->status === OrderStatus::Paid, 404);

        return view('tickets.show', [
            'ticket' => $ticket,
            'event' => $ticket->ticketType->eventDetails(),
            'when' => $ticket->ticketType->eventWhen(),
            'discoRun' => config('lri.disco_run'),
        ]);
    }

    /**
     * The holder fills in a blank ticket through its link. A ticket that already has a name can't be taken over.
     */
    public function claim(NameTicketRequest $request, Ticket $ticket, NameTicket $nameTicket): RedirectResponse
    {
        abort_unless($ticket->order->status === OrderStatus::Paid, 404);

        try {
            $nameTicket->handle($ticket, $request->validated('name'), $request->validated('email'), onlyIfBlank: true);
        } catch (TicketNotUsable $exception) {
            return redirect()->to($ticket->claimUrl())->withErrors(['ticket' => $exception->getMessage()], 'ticket'.$ticket->id);
        }

        return redirect()->to($ticket->claimUrl())->with('status', 'Your ticket is ready. We’ve emailed it to you too.');
    }

    /**
     * The buyer names, or renames, one of their tickets from the receipt.
     */
    public function update(NameTicketRequest $request, Order $order, Ticket $ticket, NameTicket $nameTicket): RedirectResponse
    {
        $back = $order->receiptUrl().'#ticket-'.$ticket->id;

        try {
            $ticket = $nameTicket->handle($ticket, $request->validated('name'), $request->validated('email'));
        } catch (TicketNotUsable $exception) {
            return redirect()->to($back)->withErrors(['ticket' => $exception->getMessage()], 'ticket'.$ticket->id);
        }

        return redirect()->to($back)->with('status', $ticket->isBuyers()
            ? 'Saved.'
            : 'Saved. We’ve emailed '.$ticket->holder_name.' their ticket.');
    }
}

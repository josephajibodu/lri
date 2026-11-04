<?php

namespace App\Http\Controllers;

use App\Actions\Tickets\ChangeTicketLink;
use App\Actions\Tickets\NameTicket;
use App\Enums\OrderStatus;
use App\Exceptions\TicketNotUsable;
use App\Http\Requests\NameTicketRequest;
use App\Models\Order;
use App\Models\Ticket;
use Illuminate\Http\RedirectResponse;
use Illuminate\View\View;

/**
 * Putting a name on each ticket: by the buyer from their receipt, or by the holder from the ticket's own link. The
 * buyer also controls each ticket's link and code from the receipt: sending, cancelling and reissuing.
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

    /**
     * The buyer sent a blank ticket's link to someone; the receipt now says "Link sent".
     */
    public function shareLink(Order $order, Ticket $ticket, ChangeTicketLink $changeTicketLink): RedirectResponse
    {
        return $this->changeFromReceipt($order, $ticket, fn () => $changeTicketLink->share($ticket), 'Link ready. Send it to the person this ticket is for.');
    }

    /**
     * The buyer stops a sent link working before anyone fills it in.
     */
    public function cancelLink(Order $order, Ticket $ticket, ChangeTicketLink $changeTicketLink): RedirectResponse
    {
        return $this->changeFromReceipt($order, $ticket, fn () => $changeTicketLink->cancel($ticket), 'That link no longer works. Type their details or send a new link.');
    }

    /**
     * The buyer gives a ticket a new code and link, so the old ones stop working.
     */
    public function reissue(Order $order, Ticket $ticket, ChangeTicketLink $changeTicketLink): RedirectResponse
    {
        return $this->changeFromReceipt($order, $ticket, fn () => $changeTicketLink->reissue($ticket), 'Reissued with a new code. The old code, QR and link no longer work. Name the ticket again or send its new link.');
    }

    /**
     * Run one of the buyer's ticket changes and go back to that ticket on the receipt.
     *
     * @param  callable(): Ticket  $change
     */
    private function changeFromReceipt(Order $order, Ticket $ticket, callable $change, string $done): RedirectResponse
    {
        $back = $order->receiptUrl().'#ticket-'.$ticket->id;

        try {
            $change();
        } catch (TicketNotUsable $exception) {
            return redirect()->to($back)->withErrors(['ticket' => $exception->getMessage()], 'ticket'.$ticket->id);
        }

        return redirect()->to($back)->with('status', $done);
    }
}

<?php

namespace App\Mail;

use App\Models\Order;
use App\Models\Ticket;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

/**
 * The buyer's tickets, with a code per ticket and a link to their receipt.
 */
class TicketsMail extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(public Order $order) {}

    /**
     * Get the message envelope.
     */
    public function envelope(): Envelope
    {
        $event = $this->order->ticketType->eventDetails();

        return new Envelope(
            subject: 'Your '.($event['title'] ?? $this->order->ticketType->name).' '.($this->order->quantity === 1 ? 'ticket' : 'tickets').' · '.$this->order->reference,
        );
    }

    /**
     * Get the message content definition.
     */
    public function content(): Content
    {
        $event = $this->order->ticketType->eventDetails();

        return new Content(
            markdown: 'mail.tickets',
            with: [
                'eventName' => $event['title'] ?? $this->order->ticketType->name,
                'bannerUrl' => filled($event['email_banner'] ?? null) ? asset($event['email_banner']) : null,
                'accent' => $event['email_accent'] ?? null,
                'gradient' => $event['email_gradient'] ?? null,
                'tagline' => $event['tagline'] ?? null,
                'channels' => $event['channels'] ?? [],
                'details' => array_filter([
                    'When' => $this->order->ticketType->eventWhen(),
                    'Where' => $event['venue'] ?? null,
                ]),
                'receipt' => [
                    'Paid' => $this->order->quantity.' × '.$this->order->unitPrice()->format().' = '.$this->order->total()->format(),
                    'Order' => $this->order->reference,
                ],
                'receiptUrl' => $this->order->receiptUrl(),
                'unnamedTickets' => $this->order->tickets->reject->isNamed()->count(),
                'ownTicket' => $this->order->tickets->first(fn (Ticket $ticket): bool => $ticket->isNamed() && $ticket->isBuyers()),
            ],
        );
    }
}

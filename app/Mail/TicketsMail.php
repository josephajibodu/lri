<?php

namespace App\Mail;

use App\Models\Order;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Carbon;

/**
 * The buyer's tickets, with a code per ticket and a link back to their order page.
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
        $startsAt = filled($event['starts_at'] ?? null) ? Carbon::parse($event['starts_at'], $event['timezone']) : null;

        return new Content(
            markdown: 'mail.tickets',
            with: [
                'event' => $event,
                'when' => $startsAt?->format('l j F, g:i A') ?? ($event['date_window'] ?? null),
                'orderUrl' => $this->order->statusUrl(),
            ],
        );
    }
}

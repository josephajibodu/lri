<?php

namespace App\Mail;

use App\Models\Ticket;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

/**
 * One person's ticket, sent to them when someone else bought it and their name goes on it.
 */
class TicketMail extends Mailable
{
    use Queueable, SerializesModels;

    /**
     * The number of times the job may be attempted.
     */
    public int $tries = 5;

    /**
     * Seconds to wait before each retry.
     *
     * @var list<int>
     */
    public array $backoff = [60, 300, 900, 3600];

    public function __construct(public Ticket $ticket) {}

    /**
     * Get the message envelope.
     */
    public function envelope(): Envelope
    {
        return new Envelope(
            subject: 'Your '.$this->eventName().' ticket · '.$this->ticket->code,
        );
    }

    /**
     * Get the message content definition.
     */
    public function content(): Content
    {
        $event = $this->ticket->ticketType->eventDetails();

        return new Content(
            markdown: 'mail.ticket',
            with: [
                'eventName' => $this->eventName(),
                'buyerName' => $this->ticket->order->buyer_name,
                'bannerUrl' => filled($event['email_banner'] ?? null) ? asset($event['email_banner']) : null,
                'accent' => $event['email_accent'] ?? null,
                'details' => array_filter([
                    'Name' => $this->ticket->holder_name,
                    'When' => $this->ticket->ticketType->eventWhen(),
                    'Where' => $event['venue'] ?? null,
                ]),
                'ticketUrl' => $this->ticket->claimUrl(),
            ],
        );
    }

    private function eventName(): string
    {
        return $this->ticket->ticketType->eventDetails()['title'] ?? $this->ticket->ticketType->name;
    }
}

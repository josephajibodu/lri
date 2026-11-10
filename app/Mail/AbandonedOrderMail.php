<?php

namespace App\Mail;

use App\Models\Order;
use App\Support\PersonName;
use App\Support\SiteAsset;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\URL;
use Illuminate\Support\Str;

/**
 * The one reminder an organiser can send the buyer of an abandoned order: their tickets weren't paid for, they're
 * still on sale, and the button takes them back to the ticket form with their details filled in.
 */
class AbandonedOrderMail extends Mailable
{
    use Queueable, SerializesModels;

    /**
     * How many days the button keeps working.
     */
    public const LinkLifetimeDays = 30;

    /**
     * @param  string|null  $buttonUrl  Where the button goes. Left out, it's a signed link to pick up this order.
     */
    public function __construct(public Order $order, public ?string $buttonUrl = null) {}

    /**
     * Get the message envelope.
     */
    public function envelope(): Envelope
    {
        return new Envelope(
            subject: 'Still coming to the '.$this->eventName().'? Your '.Str::plural('ticket', $this->order->quantity).' aren’t paid for yet',
        );
    }

    /**
     * Get the message content definition. The price is today's, since that's what the buyer will pay now.
     */
    public function content(): Content
    {
        $ticketType = $this->order->ticketType;
        $event = $ticketType->eventDetails();
        $coupon = $this->order->coupon;

        return new Content(
            markdown: 'mail.abandoned-order',
            with: [
                'eventName' => $this->eventName(),
                'firstName' => Str::before(PersonName::forEmail($this->order->buyer_name), ' '),
                'tickets' => $this->order->quantity === 1 ? 'a ticket' : $this->order->quantity.' tickets',
                'bannerUrl' => filled($event['email_banner'] ?? null) ? SiteAsset::url($event['email_banner']) : null,
                'accent' => $event['email_accent'] ?? null,
                'gradient' => $event['email_gradient'] ?? null,
                'tagline' => $event['tagline'] ?? null,
                'channels' => $event['channels'] ?? [],
                'details' => array_filter([
                    'Tickets' => $this->order->quantity.' × '.$ticketType->unitPrice()->format(),
                    'Coupon' => $coupon ? "{$coupon->code}, filled in for you" : null,
                    'When' => $ticketType->eventWhen(),
                    'Where' => $event['venue'] ?? null,
                ]),
                'helpUrl' => filled(config('lri.whatsapp_chat.number')) ? 'https://wa.me/'.config('lri.whatsapp_chat.number') : null,
                'pickUpUrl' => $this->buttonUrl ?? URL::temporarySignedRoute(
                    'orders.pick-up',
                    now()->addDays(self::LinkLifetimeDays),
                    ['order' => $this->order->reference],
                ),
            ],
        );
    }

    private function eventName(): string
    {
        return $this->order->ticketType->eventDetails()['title'] ?? $this->order->ticketType->name;
    }
}

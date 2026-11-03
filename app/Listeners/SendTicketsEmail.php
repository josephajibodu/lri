<?php

namespace App\Listeners;

use App\Events\OrderPaid;
use App\Mail\TicketsMail;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Support\Facades\Mail;

/**
 * Email the buyer their tickets once the order is paid. It runs on the queue, so a slow mail server never holds
 * up a webhook, and it retries when sending fails.
 */
class SendTicketsEmail implements ShouldQueue
{
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

    /**
     * Handle the event.
     */
    public function handle(OrderPaid $event): void
    {
        $order = $event->order->load(['ticketType', 'tickets']);

        Mail::to($order->buyer_email, $order->buyer_name)->send(new TicketsMail($order));

        $order->update(['tickets_emailed_at' => now()]);
    }
}

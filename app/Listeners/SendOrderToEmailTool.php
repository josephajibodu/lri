<?php

namespace App\Listeners;

use App\EmailTools\EmailToolManager;
use App\Enums\EmailToolEvent;
use App\Events\OrderAbandoned;
use App\Events\OrderPaid;
use App\Events\OrderPlaced;
use App\Jobs\SendToEmailTool;
use App\Models\EmailToolSend;

/**
 * Note the news for the email tool and queue it, only once per order: an order that lapses twice doesn't start a
 * second round of follow-ups. Every buyer who pays joins the event's emails, which carry an unsubscribe link. Before
 * paying, only someone who ticked the old consent box is passed on, so nobody is asked to confirm a subscription
 * while they're still paying.
 */
class SendOrderToEmailTool
{
    public function __construct(private EmailToolManager $tools) {}

    /**
     * Handle the event.
     */
    public function handle(OrderPlaced|OrderAbandoned|OrderPaid $event): void
    {
        if (! $this->tools->isEnabled() || (! $event instanceof OrderPaid && ! $event->order->marketing_consent)) {
            return;
        }

        $send = EmailToolSend::query()->createOrFirst([
            'order_id' => $event->order->id,
            'tool' => $this->tools->getDefaultDriver(),
            'event' => match ($event::class) {
                OrderPlaced::class => EmailToolEvent::CheckoutStarted,
                OrderAbandoned::class => EmailToolEvent::CheckoutAbandoned,
                OrderPaid::class => EmailToolEvent::OrderPaid,
            },
        ]);

        if ($send->wasRecentlyCreated) {
            SendToEmailTool::dispatch($send);
        }
    }
}

<?php

namespace App\Actions\Checkout;

use App\Enums\CheckoutStep;
use App\Mail\AbandonedOrderMail;
use App\Models\Order;
use App\Models\TicketType;
use App\Support\AdminActivity;
use Illuminate\Support\Facades\Mail;

/**
 * Email the buyer of an abandoned order the one reminder to come back and finish. It only goes if the order is still
 * waiting for one (see Order::scopeAwaitingReminder), and only once, even if two organisers press send together.
 */
class SendOrderReminder
{
    public function __construct(private RecordCheckoutStep $recordCheckoutStep) {}

    /**
     * Whether there are tickets to come back to. With none on sale, the reminder's button would lead nowhere.
     */
    public static function ticketsOnSale(): bool
    {
        return TicketType::query()->onSale()->where('event', 'disco-run')->get()
            ->contains(fn (TicketType $ticketType): bool => $ticketType->maxQuantity() > 0);
    }

    /**
     * Send the reminder. Returns false, sending nothing, when the order doesn't need one.
     */
    public function handle(Order $order): bool
    {
        if (! Order::query()->awaitingReminder()->whereKey($order->id)->exists()) {
            return false;
        }

        // Claimed by a separate update, since MySQL can't update a table while a subquery reads it.
        $claimed = Order::query()->whereKey($order->id)->whereNull('reminder_sent_at')->update(['reminder_sent_at' => now()]);

        if ($claimed === 0) {
            return false;
        }

        $order->refresh()->load(['ticketType', 'coupon']);

        Mail::to($order->buyer_email, $order->buyer_name)->queue(new AbandonedOrderMail($order));

        $this->recordCheckoutStep->handle(CheckoutStep::ReminderSent, order: $order);
        AdminActivity::record("Emailed a reminder to finish order {$order->reference}, to {$order->buyer_email}", $order);

        return true;
    }
}

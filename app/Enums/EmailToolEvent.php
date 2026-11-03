<?php

namespace App\Enums;

use App\Models\Order;
use Filament\Support\Contracts\HasLabel;

/**
 * The news about a buyer that an email tool gets, in our own words. Each tool translates these into its own terms.
 */
enum EmailToolEvent: string implements HasLabel
{
    case CheckoutStarted = 'checkout_started';
    case CheckoutAbandoned = 'checkout_abandoned';
    case OrderPaid = 'order_paid';

    /**
     * The news as organisers read it.
     */
    public function label(): string
    {
        return match ($this) {
            self::CheckoutStarted => 'Checkout started',
            self::CheckoutAbandoned => 'Checkout abandoned',
            self::OrderPaid => 'Paid',
        };
    }

    /**
     * Whether this is still true of the order. A retry can run long after the event, and a late "abandoned"
     * must never reach the tool after the buyer has paid. "Paid" always goes, so follow-ups stop.
     */
    public function stillAppliesTo(Order $order): bool
    {
        return match ($this) {
            self::CheckoutStarted => $order->status === OrderStatus::Pending,
            self::CheckoutAbandoned => $order->status === OrderStatus::Abandoned,
            self::OrderPaid => true,
        };
    }

    public function getLabel(): string
    {
        return $this->label();
    }
}

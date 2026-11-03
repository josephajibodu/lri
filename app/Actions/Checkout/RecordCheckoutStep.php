<?php

namespace App\Actions\Checkout;

use App\Enums\CheckoutStep;
use App\Enums\PaymentMethod;
use App\Models\CheckoutEvent;
use App\Models\Order;
use App\Models\Payment;
use Throwable;

/**
 * Note a step of a buyer's journey. Tracking must never get in the way of a sale, so a failure here is only reported.
 */
class RecordCheckoutStep
{
    /**
     * @param  array<string, scalar|list<scalar>|null>  $properties  Details of the step, never anything that identifies the buyer.
     */
    public function handle(
        CheckoutStep $step,
        ?string $visitorId = null,
        ?Order $order = null,
        ?Payment $payment = null,
        ?PaymentMethod $method = null,
        array $properties = [],
    ): ?CheckoutEvent {
        try {
            return CheckoutEvent::query()->create([
                'visitor_id' => $visitorId ?? $order?->visitor_id,
                'order_id' => $order?->id ?? $payment?->order_id,
                'payment_id' => $payment?->id,
                'step' => $step,
                'method' => $method ?? $payment?->method,
                'properties' => array_filter($properties, fn (mixed $value): bool => $value !== null) ?: null,
                'occurred_at' => now(),
            ]);
        } catch (Throwable $exception) {
            report($exception);

            return null;
        }
    }
}

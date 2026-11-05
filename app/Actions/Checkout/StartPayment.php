<?php

namespace App\Actions\Checkout;

use App\Enums\OrderStatus;
use App\Enums\PaymentFailureReason;
use App\Enums\PaymentStatus;
use App\Exceptions\CheckoutUnavailable;
use App\Exceptions\TicketsUnavailable;
use App\Models\Coupon;
use App\Models\Order;
use App\Models\PaymentChannel;
use App\Models\TicketType;
use App\Payments\Data\CheckoutRequest;
use App\Payments\Exceptions\PaymentProviderException;
use App\Payments\PaymentManager;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * Open a checkout for an order with the provider behind the chosen channel, as a new payment attempt.
 * An order whose hold has lapsed gets its tickets held again first, if there are still enough left.
 */
class StartPayment
{
    public function __construct(private PaymentManager $payments) {}

    /**
     * @throws CheckoutUnavailable
     * @throws TicketsUnavailable
     */
    public function handle(Order $order, PaymentChannel $channel): StartedPayment
    {
        if (in_array($order->status, [OrderStatus::Paid, OrderStatus::Refunded], true)) {
            throw CheckoutUnavailable::alreadyPaid();
        }

        if (! $channel->is_enabled || $channel->currency !== $order->currency) {
            throw CheckoutUnavailable::methodUnavailable();
        }

        $this->holdTickets($order);

        $provider = $this->payments->forChannel($channel);
        $expiresInMinutes = config('payments.checkout_expires_in_minutes');

        $payment = $order->payments()->create([
            'provider' => $provider->name(),
            'method' => $channel->method,
            'status' => PaymentStatus::Open,
            'amount' => $order->amount,
            'currency' => $order->currency,
        ]);

        try {
            $session = $provider->createCheckout(new CheckoutRequest(
                reference: $payment->reference,
                amount: $order->total(),
                customerEmail: $order->buyer_email,
                customerName: $order->buyer_name,
                customerPhone: $order->buyer_phone,
                successUrl: $order->statusUrl(['from' => 'checkout']),
                cancelUrl: $order->statusUrl(['from' => 'cancelled']),
                methods: [$channel->method],
                expiresInMinutes: $expiresInMinutes,
                metadata: array_filter(['order' => $order->reference, 'ticket_type' => $order->ticketType->slug, 'coupon' => $order->coupon?->code]),
            ));
        } catch (PaymentProviderException $exception) {
            report($exception);

            $payment->update([
                'status' => PaymentStatus::Failed,
                'failure_reason' => PaymentFailureReason::ProviderError,
                'failure_message' => Str::limit($exception->getMessage(), 250),
                'failed_at' => now(),
            ]);

            throw CheckoutUnavailable::providerDown($exception);
        }

        $expiresAt = $session->expiresAt ?? now()->addMinutes($expiresInMinutes);

        $payment->update([
            'provider_checkout_id' => $session->checkoutId,
            'checkout_url' => $session->checkoutUrl,
            'expires_at' => $expiresAt,
        ]);

        if ($order->expires_at === null || $order->expires_at->lt($expiresAt)) {
            $order->update(['expires_at' => $expiresAt]);
        }

        return new StartedPayment($payment, $session);
    }

    /**
     * Hold the order's tickets again when its hold has lapsed or it was abandoned. Its coupon gave up its use when the
     * hold lapsed, so it's checked again: one that no longer works comes off, and the buyer sees the new total before
     * being asked to pay it.
     *
     * @throws TicketsUnavailable
     * @throws CheckoutUnavailable
     */
    private function holdTickets(Order $order): void
    {
        if ($order->status === OrderStatus::Pending && $order->expires_at?->isFuture()) {
            return;
        }

        $endedCoupon = DB::transaction(function () use ($order): ?Coupon {
            $ticketType = TicketType::query()->onSale()->lockForUpdate()->find($order->ticket_type_id)
                ?? throw TicketsUnavailable::notOnSale();

            $ticketsLeft = $ticketType->ticketsLeft();

            if ($ticketsLeft !== null && $ticketsLeft < $order->quantity) {
                throw TicketsUnavailable::notEnough($ticketsLeft);
            }

            $coupon = $order->coupon_id === null ? null : Coupon::query()->lockForUpdate()->find($order->coupon_id);
            $couponEnded = $coupon?->whyUnusableOn($ticketType) !== null;

            $order->update([
                'status' => OrderStatus::Pending,
                'abandoned_at' => null,
                'expires_at' => now()->addMinutes(config('payments.checkout_expires_in_minutes')),
                ...($couponEnded ? ['coupon_id' => null, 'discount_amount' => 0, 'amount' => $order->subtotal()->amount] : []),
            ]);

            return $couponEnded ? $coupon : null;
        });

        if ($endedCoupon !== null) {
            $order->unsetRelation('coupon');

            throw CheckoutUnavailable::couponEnded($endedCoupon->code, $order->total()->format());
        }
    }
}

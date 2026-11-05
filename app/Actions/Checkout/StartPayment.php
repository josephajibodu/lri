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
use App\Support\Money;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * Open a checkout for an order with the provider behind the chosen channel, as a new payment attempt. Every attempt
 * checks the order again first, because each one keeps its tickets held for longer: tickets must still be on sale, the
 * order can only be tried so many times, and a coupon that has ended comes off. An order whose hold has lapsed gets its
 * tickets held again first, if there are still enough left, at today's price.
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
        $this->refuseIfSettled($order);

        if (! $channel->is_enabled || $channel->currency !== $order->currency) {
            throw CheckoutUnavailable::methodUnavailable();
        }

        $this->prepareOrder($order);

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
     * Check the order can still be paid for, and bring it up to date. The order's row is locked and read afresh first,
     * so a payment landing at this very moment can't be undone by what this request read earlier. A change to what the
     * buyer owes is saved, then explained to them before they're asked to pay it.
     *
     * @throws CheckoutUnavailable
     * @throws TicketsUnavailable
     */
    private function prepareOrder(Order $order): void
    {
        $changedTotal = DB::transaction(function () use ($order): ?CheckoutUnavailable {
            $locked = Order::query()->lockForUpdate()->findOrFail($order->id);

            $this->refuseIfSettled($locked);

            if ($locked->payments()->whereNotNull('provider_checkout_id')->count() >= config('payments.unpaid_limits.attempts_per_order')) {
                throw CheckoutUnavailable::tooManyAttempts();
            }

            $ticketType = TicketType::query()->onSale()->lockForUpdate()->find($locked->ticket_type_id)
                ?? throw TicketsUnavailable::notOnSale();
            $coupon = $locked->coupon_id === null ? null : Coupon::query()->lockForUpdate()->find($locked->coupon_id);

            return $locked->status === OrderStatus::Pending && $locked->expires_at?->isFuture()
                ? $this->dropEndedCoupon($locked, $coupon)
                : $this->holdTicketsAgain($locked, $ticketType, $coupon);
        });

        $order->refresh();

        if ($changedTotal !== null) {
            throw $changedTotal;
        }
    }

    /**
     * An order whose tickets are held keeps its price. But a coupon that has ended comes off the next time it asks to
     * pay, or a discount could be kept alive for ever by asking again before each hold runs out.
     */
    private function dropEndedCoupon(Order $order, ?Coupon $coupon): ?CheckoutUnavailable
    {
        if ($coupon === null || ! $coupon->hasEnded()) {
            return null;
        }

        $order->update(['coupon_id' => null, 'discount_amount' => 0, 'amount' => $order->subtotal()->amount]);

        return CheckoutUnavailable::couponEnded($coupon->code, $order->total()->format());
    }

    /**
     * Hold the order's tickets again when its hold has lapsed or it was abandoned, at today's price. Its coupon gave up
     * its use when the hold lapsed, so it's checked again: one that no longer works comes off.
     *
     * @throws TicketsUnavailable
     */
    private function holdTicketsAgain(Order $order, TicketType $ticketType, ?Coupon $coupon): ?CheckoutUnavailable
    {
        $ticketsLeft = $ticketType->ticketsLeft();

        if ($ticketsLeft !== null && $ticketsLeft < $order->quantity) {
            throw TicketsUnavailable::notEnough($ticketsLeft);
        }

        $couponEnded = $coupon?->whyUnusableOn($ticketType) !== null;
        $unitPrice = $ticketType->unitPrice();
        $discount = $couponEnded || $coupon === null ? Money::of(0, $order->currency) : $coupon->discountFor($unitPrice, $order->quantity);
        $priceChanged = $unitPrice->amount !== $order->unit_price;

        $order->update([
            'status' => OrderStatus::Pending,
            'abandoned_at' => null,
            'expires_at' => now()->addMinutes(config('payments.checkout_expires_in_minutes')),
            'unit_price' => $unitPrice->amount,
            'coupon_id' => $couponEnded ? null : $order->coupon_id,
            'discount_amount' => $discount->amount,
            'amount' => $unitPrice->times($order->quantity)->amount - $discount->amount,
        ]);

        return match (true) {
            $couponEnded => CheckoutUnavailable::couponEnded($coupon->code, $order->total()->format()),
            $priceChanged => CheckoutUnavailable::priceChanged($order->total()->format()),
            default => null,
        };
    }

    /**
     * @throws CheckoutUnavailable
     */
    private function refuseIfSettled(Order $order): void
    {
        if (in_array($order->status, [OrderStatus::Paid, OrderStatus::Refunded, OrderStatus::Disputed], true)) {
            throw CheckoutUnavailable::alreadyPaid();
        }
    }
}

<?php

namespace App\Actions\Checkout;

use App\Enums\CheckoutStep;
use App\Enums\OrderStatus;
use App\Enums\PaymentFailureReason;
use App\Enums\PaymentStatus;
use App\Events\OrderPaid;
use App\Models\Order;
use App\Models\Payment;
use App\Payments\Data\PaymentUpdate;
use App\Support\Money;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

/**
 * Apply what a provider says about a payment attempt, and keep its order in step: tickets are issued once it is paid,
 * stop working when the money goes back, and are put on hold while the buyer's bank disputes it. Webhooks and direct
 * status checks both come through here, in any order and any number of times: an update that would move a payment
 * backwards is ignored, and an order is only ever fulfilled once.
 */
class RecordPaymentUpdate
{
    /**
     * How many times to run the update if the database picks it to back out of a clash with another one.
     */
    private const TransactionAttempts = 3;

    public function __construct(private RecordCheckoutStep $recordCheckoutStep) {}

    public function handle(Payment $payment, PaymentUpdate $update): Payment
    {
        $orderWasPaid = DB::transaction(function () use ($payment, $update): bool {
            $payment = Payment::query()->lockForUpdate()->findOrFail($payment->id);
            $order = Order::query()->lockForUpdate()->findOrFail($payment->order_id);
            $payment->setRelation('order', $order);
            [$status, $failureReason, $failureMessage] = $this->outcome($payment, $update);

            if (! $this->canMove($payment->status, $status, $update)) {
                $payment->update(['last_checked_at' => now()]);

                return false;
            }

            $previousStatus = $payment->status;
            $previousFailure = $payment->failure_reason;

            $payment->update([
                'status' => $status,
                'method' => $update->method ?? $payment->method,
                'provider_payment_id' => $update->paymentId ?? $payment->provider_payment_id,
                'provider_status' => $update->providerStatus,
                'fee' => $update->fee?->currency === $payment->currency ? $update->fee->amount : $payment->fee,
                'failure_reason' => $status === PaymentStatus::Succeeded ? null : ($failureReason ?? $payment->failure_reason),
                'failure_message' => $status === PaymentStatus::Succeeded ? null : ($failureMessage ?? $payment->failure_message),
                'paid_at' => $status === PaymentStatus::Succeeded ? ($payment->paid_at ?? $update->occurredAt ?? now()) : $payment->paid_at,
                'failed_at' => $status === PaymentStatus::Failed ? ($payment->failed_at ?? $update->occurredAt ?? now()) : $payment->failed_at,
                'last_checked_at' => now(),
            ]);

            $this->recordStep($payment, $previousStatus, $previousFailure);

            return match ($status) {
                PaymentStatus::Succeeded => $update->endsDispute ? $this->endDispute($order) : $this->fulfil($order, $payment),
                PaymentStatus::Refunded => $this->refund($order, $payment),
                PaymentStatus::PartiallyRefunded => $this->refundPart($order, $payment),
                PaymentStatus::Disputed => $this->holdForDispute($order, $payment),
                default => false,
            };
        }, self::TransactionAttempts);

        $payment = $payment->fresh();

        if ($orderWasPaid) {
            OrderPaid::dispatch($payment->order);
        }

        return $payment;
    }

    /**
     * Note the step in the buyer's journey when the payment settles one way or another, or a try fails on a checkout
     * that is still open. Repeats of the same news are not noted twice.
     */
    private function recordStep(Payment $payment, PaymentStatus $previousStatus, ?PaymentFailureReason $previousFailure): void
    {
        $isNewFailedTry = $payment->status === PaymentStatus::Open && $payment->failure_reason !== null && $payment->failure_reason !== $previousFailure;

        if ($payment->status === $previousStatus && ! $isNewFailedTry) {
            return;
        }

        $step = match ($payment->status) {
            PaymentStatus::Succeeded => CheckoutStep::PaymentSucceeded,
            PaymentStatus::Failed, PaymentStatus::Underpaid, PaymentStatus::Open => CheckoutStep::PaymentFailed,
            PaymentStatus::Expired, PaymentStatus::Abandoned => CheckoutStep::CheckoutExpired,
            default => null,
        };

        if ($step === null) {
            return;
        }

        $order = $payment->order;

        $this->recordCheckoutStep->handle($step, $order->visitor_id, $order, $payment, properties: [
            'reason' => $payment->failure_reason?->value,
            'can_retry' => $step === CheckoutStep::PaymentFailed ? $payment->status === PaymentStatus::Open : null,
            'minutes_to_pay' => $step === CheckoutStep::PaymentSucceeded ? (int) round($payment->created_at->diffInMinutes($payment->paid_at ?? now())) : null,
        ]);
    }

    /**
     * The status to record, with the failure reason. A payment that says it succeeded but brought in less than the
     * order's total, or a different currency, is recorded as underpaid and not fulfilled. One that doesn't say how much
     * it brought in isn't believed: the payment stays as it was until the provider says how much arrived.
     *
     * @return array{0: PaymentStatus, 1: ?PaymentFailureReason, 2: ?string}
     */
    private function outcome(Payment $payment, PaymentUpdate $update): array
    {
        if ($update->status === PaymentStatus::Succeeded && $update->amount === null) {
            if ($payment->status !== PaymentStatus::Succeeded) {
                $this->flag($payment->order, "Bachs said payment {$payment->reference} succeeded without saying how much arrived, so no tickets were issued for it.", $payment);
            }

            return [$payment->status, null, null];
        }

        $isShort = $update->amount !== null
            && ($update->amount->currency !== $payment->currency || $update->amount->amount < $payment->amount);

        if ($update->status === PaymentStatus::Succeeded && $isShort) {
            return [
                PaymentStatus::Underpaid,
                PaymentFailureReason::AmountMismatch,
                "Expected {$payment->money()->format()}, the provider reported {$update->amount->format()}",
            ];
        }

        if ($update->status === PaymentStatus::Refunded && $this->refundsOnlyPartOf($payment, $update)) {
            return [PaymentStatus::PartiallyRefunded, null, null];
        }

        return [$update->status, $update->failureReason, $update->failureMessage ? Str::limit($update->failureMessage, 250) : null];
    }

    /**
     * Whether a refund left the buyer with most of what they paid. Bachs keeps its fee when it refunds, so getting back
     * everything except the fee is still a full refund. A refund that doesn't say how much counts as a full one.
     */
    private function refundsOnlyPartOf(Payment $payment, PaymentUpdate $update): bool
    {
        return $update->refundedAmount !== null
            && Money::fromDecimal($update->refundedAmount, $payment->currency)->amount < $payment->amount - ($payment->fee ?? 0);
    }

    /**
     * Whether a payment can go from one status to another. Late money can still turn a failed or expired attempt
     * into a paid one (a bank transfer can land after the checkout closes). A paid attempt can only be refunded or
     * disputed, and a disputed one only counts as paid again if the bank rules for us.
     */
    private function canMove(PaymentStatus $from, PaymentStatus $to, PaymentUpdate $update): bool
    {
        if ($from === $to) {
            return true;
        }

        return match ($from) {
            PaymentStatus::Open => true,
            PaymentStatus::Failed, PaymentStatus::Expired, PaymentStatus::Abandoned => $to !== PaymentStatus::Open,
            PaymentStatus::Underpaid => in_array($to, [PaymentStatus::Succeeded, PaymentStatus::Refunded, PaymentStatus::PartiallyRefunded], true),
            PaymentStatus::Succeeded => in_array($to, [PaymentStatus::Refunded, PaymentStatus::PartiallyRefunded, PaymentStatus::Disputed], true),
            PaymentStatus::PartiallyRefunded => in_array($to, [PaymentStatus::Refunded, PaymentStatus::Disputed], true),
            PaymentStatus::Disputed => in_array($to, [PaymentStatus::Refunded, PaymentStatus::PartiallyRefunded], true) || ($to === PaymentStatus::Succeeded && $update->endsDispute),
            PaymentStatus::Refunded => false,
        };
    }

    /**
     * Mark the order paid and issue one ticket per seat, the first one in the buyer's name. Returns whether this call
     * did it. Money arriving for an order that was refunded brings back the tickets it already has, not new ones.
     */
    private function fulfil(Order $order, Payment $payment): bool
    {
        if ($order->status === OrderStatus::Paid) {
            if ($order->payments()->where('status', PaymentStatus::Succeeded)->whereKeyNot($payment->id)->exists()) {
                $this->flag($order, 'This order was paid for twice; one payment needs refunding.', $payment);
            }

            return false;
        }

        if ($order->status === OrderStatus::Disputed) {
            $this->flag($order, "Payment {$payment->reference} arrived while this order’s payment is disputed. Its tickets stay on hold, so check whether it needs refunding.", $payment);

            return false;
        }

        if ($order->status === OrderStatus::Refunded) {
            $this->flag($order, "Payment {$payment->reference} arrived after this order was refunded, so its tickets work again. Check whether it needs refunding.", $payment);
        }

        $order->update([
            'status' => OrderStatus::Paid,
            'paid_at' => $payment->paid_at,
            'abandoned_at' => null,
            'refunded_at' => null,
        ]);

        for ($seat = $order->tickets()->count() + 1; $seat <= $order->quantity; $seat++) {
            $order->tickets()->create([
                'ticket_type_id' => $order->ticket_type_id,
                'holder_name' => $seat === 1 ? $order->buyer_name : null,
                'holder_email' => $seat === 1 ? $order->buyer_email : null,
            ]);
        }

        $this->flagWhatLateMoneyBroke($order, $payment);

        return true;
    }

    /**
     * A payment that lands after its hold ran out is still honoured, since the buyer has paid. But the tickets and
     * coupon use it was holding may have gone to someone else by then, or its order's total may have changed, so an
     * organiser gets to decide what to do about it.
     */
    private function flagWhatLateMoneyBroke(Order $order, Payment $payment): void
    {
        $capacity = $order->ticketType->capacity;
        $oversoldBy = $capacity === null ? 0 : $order->ticketType->ticketsTaken() - $capacity;
        $coupon = $order->coupon;

        if ($oversoldBy > 0) {
            $this->flag($order, "This payment landed after its hold ran out, and the event is now oversold by {$oversoldBy}.", $payment);
        }

        if ($coupon?->max_orders !== null && $coupon->timesTaken() > $coupon->max_orders) {
            $this->flag($order, "This payment landed after its hold ran out, and coupon {$coupon->code} is now over its limit of {$coupon->max_orders}.", $payment);
        }

        if ($payment->amount < $order->amount) {
            $this->flag($order, "The buyer paid {$payment->money()->format()} on an earlier checkout, but the order’s total is now {$order->total()->format()}.", $payment);
        }
    }

    /**
     * All the money went back, so the tickets stop working, go back on sale and give back their coupon use. An order
     * paid for twice keeps its tickets when only the extra payment is refunded.
     */
    private function refund(Order $order, Payment $payment): bool
    {
        if (! in_array($order->status, [OrderStatus::Paid, OrderStatus::Disputed], true)) {
            return false;
        }

        if ($order->payments()->whereKeyNot($payment->id)->whereIn('status', [PaymentStatus::Succeeded, PaymentStatus::PartiallyRefunded])->exists()) {
            $this->flag($order, "Payment {$payment->reference} was refunded, but another payment still covers this order, so its tickets still work.", $payment);

            return false;
        }

        $order->update(['status' => OrderStatus::Refunded, 'refunded_at' => now()]);

        $collected = $order->tickets()->whereNotNull('checked_in_at')->count();

        if ($collected > 0) {
            $this->flag($order, "This order was refunded after {$collected} of its headsets were handed out.", $payment);
        }

        return false;
    }

    /**
     * Some of the money went back. The tickets keep working until an organiser decides otherwise, including when this
     * settles a dispute over only part of the payment.
     */
    private function refundPart(Order $order, Payment $payment): bool
    {
        if ($order->status === OrderStatus::Disputed) {
            $order->update(['status' => OrderStatus::Paid]);
        }

        $this->flag($order, "Part of payment {$payment->reference} was refunded. Its tickets still work, so check whether any should be cancelled.", $payment);

        return false;
    }

    /**
     * The buyer's bank is disputing the payment and Bachs has set the money aside, so the tickets wait for the outcome.
     */
    private function holdForDispute(Order $order, Payment $payment): bool
    {
        if ($order->status === OrderStatus::Paid) {
            $order->update(['status' => OrderStatus::Disputed]);
        }

        $this->flag($order, "The buyer’s bank is disputing payment {$payment->reference} ({$payment->provider_status}). Its tickets are on hold until that’s settled.", $payment);

        return false;
    }

    /**
     * The bank ruled for us, so the tickets work again. They were issued long ago, so nothing is sent.
     */
    private function endDispute(Order $order): bool
    {
        if ($order->status === OrderStatus::Disputed) {
            $order->update(['status' => OrderStatus::Paid]);
        }

        return false;
    }

    /**
     * Leave a note on the order for organisers, and in the logs.
     */
    private function flag(Order $order, string $reason, Payment $payment): void
    {
        $order->flagForReview($reason);

        Log::warning($reason, ['order' => $order->reference, 'payment' => $payment->reference]);
    }
}

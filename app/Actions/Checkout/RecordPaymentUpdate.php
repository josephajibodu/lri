<?php

namespace App\Actions\Checkout;

use App\Enums\OrderStatus;
use App\Enums\PaymentFailureReason;
use App\Enums\PaymentStatus;
use App\Events\OrderPaid;
use App\Models\Order;
use App\Models\Payment;
use App\Payments\Data\PaymentUpdate;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

/**
 * Apply what a provider says about a payment attempt, and issue the order's tickets once it is paid.
 * Webhooks and direct status checks both come through here, in any order and any number of times:
 * an update that would move a payment backwards is ignored, and an order is only ever fulfilled once.
 */
class RecordPaymentUpdate
{
    public function handle(Payment $payment, PaymentUpdate $update): Payment
    {
        $orderWasPaid = DB::transaction(function () use ($payment, $update): bool {
            $payment = Payment::query()->lockForUpdate()->findOrFail($payment->id);
            [$status, $failureReason, $failureMessage] = $this->outcome($payment, $update);

            if (! $this->canMove($payment->status, $status)) {
                $payment->update(['last_checked_at' => now()]);

                return false;
            }

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

            return $status === PaymentStatus::Succeeded
                && $this->fulfil(Order::query()->lockForUpdate()->findOrFail($payment->order_id), $payment);
        });

        $payment = $payment->fresh();

        if ($orderWasPaid) {
            OrderPaid::dispatch($payment->order);
        }

        return $payment;
    }

    /**
     * The status to record, with the failure reason. A payment that says it succeeded but brought in less
     * than the order's total, or a different currency, is recorded as underpaid and not fulfilled.
     *
     * @return array{0: PaymentStatus, 1: ?PaymentFailureReason, 2: ?string}
     */
    private function outcome(Payment $payment, PaymentUpdate $update): array
    {
        $isShort = $update->amount !== null
            && ($update->amount->currency !== $payment->currency || $update->amount->amount < $payment->amount);

        if ($update->status === PaymentStatus::Succeeded && $isShort) {
            return [
                PaymentStatus::Underpaid,
                PaymentFailureReason::AmountMismatch,
                "Expected {$payment->money()->format()}, the provider reported {$update->amount->format()}",
            ];
        }

        return [$update->status, $update->failureReason, $update->failureMessage ? Str::limit($update->failureMessage, 250) : null];
    }

    /**
     * Whether a payment can go from one status to another. Late money can still turn a failed or expired attempt
     * into a paid one (a bank transfer can land after the checkout closes), but nothing undoes a payment except a refund.
     */
    private function canMove(PaymentStatus $from, PaymentStatus $to): bool
    {
        if ($from === $to) {
            return true;
        }

        return match ($from) {
            PaymentStatus::Open => true,
            PaymentStatus::Failed, PaymentStatus::Expired, PaymentStatus::Abandoned => $to !== PaymentStatus::Open,
            PaymentStatus::Underpaid => in_array($to, [PaymentStatus::Succeeded, PaymentStatus::Refunded, PaymentStatus::PartiallyRefunded], true),
            PaymentStatus::Succeeded => in_array($to, [PaymentStatus::Refunded, PaymentStatus::PartiallyRefunded], true),
            PaymentStatus::PartiallyRefunded => $to === PaymentStatus::Refunded,
            PaymentStatus::Refunded => false,
        };
    }

    /**
     * Mark the order paid and issue one ticket per seat. Returns whether this call did it.
     */
    private function fulfil(Order $order, Payment $payment): bool
    {
        if ($order->status === OrderStatus::Paid) {
            $paidBy = $order->payments()->where('status', PaymentStatus::Succeeded)->whereKeyNot($payment->id)->exists();

            if ($paidBy) {
                Log::warning('An order was paid for twice; one payment needs refunding.', [
                    'order' => $order->reference,
                    'payment' => $payment->reference,
                ]);
            }

            return false;
        }

        $order->update([
            'status' => OrderStatus::Paid,
            'paid_at' => $payment->paid_at,
            'abandoned_at' => null,
        ]);

        foreach (range(1, $order->quantity) as $seat) {
            $order->tickets()->create([
                'ticket_type_id' => $order->ticket_type_id,
                'holder_name' => $seat === 1 ? $order->buyer_name : null,
            ]);
        }

        return true;
    }
}

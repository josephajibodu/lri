<?php

namespace App\Console\Commands;

use App\Actions\Checkout\RecordCheckoutStep;
use App\Actions\Checkout\RefreshPayment;
use App\Enums\CheckoutStep;
use App\Enums\OrderStatus;
use App\Enums\PaymentStatus;
use App\Events\OrderAbandoned;
use App\Models\Order;
use App\Payments\Exceptions\PaymentProviderException;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;

#[Signature('orders:abandon-unpaid')]
#[Description('Mark orders abandoned once their hold has run out without payment, so follow-ups can start')]
class AbandonUnpaidOrders extends Command
{
    /**
     * How long past its hold an order waits before it counts as abandoned, so a late webhook can still land first.
     */
    private const GraceMinutes = 10;

    /**
     * Each order's open payment is checked with the provider one last time first; one that turns out paid is left alone.
     * A payment that lands after this still pays the order.
     */
    public function handle(RefreshPayment $refreshPayment, RecordCheckoutStep $recordCheckoutStep): int
    {
        $abandoned = 0;

        Order::query()
            ->where('status', OrderStatus::Pending)
            ->where('expires_at', '<', now()->subMinutes(self::GraceMinutes))
            ->with(['payments' => fn ($query) => $query->where('status', PaymentStatus::Open)])
            ->orderBy('id')
            ->lazyById(100)
            ->each(function (Order $order) use ($refreshPayment, $recordCheckoutStep, &$abandoned): void {
                foreach ($order->payments as $payment) {
                    try {
                        $refreshPayment->handle($payment);
                    } catch (PaymentProviderException $exception) {
                        report($exception);
                    }
                }

                $order->refresh();

                if ($order->status !== OrderStatus::Pending || $order->payments()->where('status', PaymentStatus::Open)->where('expires_at', '>', now())->exists()) {
                    return;
                }

                $order->update(['status' => OrderStatus::Abandoned, 'abandoned_at' => now()]);
                $recordCheckoutStep->handle(CheckoutStep::OrderAbandoned, order: $order, properties: [
                    'attempts' => $order->payments()->count(),
                ]);
                OrderAbandoned::dispatch($order);
                $abandoned++;
            });

        $this->info("Marked {$abandoned} unpaid orders abandoned.");

        return self::SUCCESS;
    }
}

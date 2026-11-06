<?php

namespace App\Reports;

use App\Enums\CheckoutStep;
use App\Enums\OrderStatus;
use App\Enums\PaymentStatus;
use App\Models\CheckoutEvent;
use App\Models\Order;
use App\Models\Payment;
use App\Support\Money;

/**
 * The headline numbers for a period: money in, tickets sold and how many visitors bought.
 */
final class SalesSummary
{
    /**
     * @return array{revenue: Money, fees: Money, paid_orders: int, tickets_sold: int, abandoned_orders: int, visitors: int, conversion_rate: ?float, average_order: ?Money}
     */
    public static function for(ReportPeriod $period, string $currency = 'NGN'): array
    {
        $paid = $period->apply(Order::query()->where('status', OrderStatus::Paid)->where('currency', $currency), 'paid_at');
        $paidOrders = (clone $paid)->count();
        $revenue = (int) (clone $paid)->sum('amount');

        $visitors = $period->apply(CheckoutEvent::query()->where('step', CheckoutStep::TicketPageViewed), 'occurred_at')
            ->distinct()
            ->count('visitor_id');

        $fees = (int) $period->apply(Payment::query()->where('status', PaymentStatus::Succeeded)->where('currency', $currency)->whereRelation('order', 'status', '!=', OrderStatus::TestPayment), 'paid_at')->sum('fee');

        return [
            'revenue' => Money::of($revenue, $currency),
            'fees' => Money::of($fees, $currency),
            'paid_orders' => $paidOrders,
            'tickets_sold' => (int) (clone $paid)->sum('quantity'),
            'abandoned_orders' => $period->apply(Order::query()->where('status', OrderStatus::Abandoned), 'abandoned_at')->count(),
            'visitors' => $visitors,
            'conversion_rate' => $visitors > 0 ? round($paidOrders / $visitors * 100, 1) : null,
            'average_order' => $paidOrders > 0 ? Money::of(intdiv($revenue, $paidOrders), $currency) : null,
        ];
    }
}

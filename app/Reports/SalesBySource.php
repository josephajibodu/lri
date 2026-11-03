<?php

namespace App\Reports;

use App\Enums\OrderStatus;
use App\Models\Order;
use App\Support\Money;
use Illuminate\Support\Collection;

/**
 * Which campaigns and sites bring buyers: orders started and paid for each source, with the money each brought in.
 */
final class SalesBySource
{
    /**
     * @return list<array{source: string, campaign: ?string, orders: int, paid_orders: int, conversion_rate: float, revenue: Money}>
     */
    public static function for(ReportPeriod $period, string $currency = 'NGN'): array
    {
        return $period->apply(Order::query()->where('currency', $currency), 'created_at')
            ->get(['id', 'status', 'amount', 'attribution'])
            ->groupBy(fn (Order $order): string => self::sourceOf($order).'|'.($order->attribution['campaign'] ?? ''))
            ->map(function (Collection $orders, string $key) use ($currency): array {
                [$source, $campaign] = explode('|', $key, 2);
                $paid = $orders->where('status', OrderStatus::Paid);

                return [
                    'source' => $source,
                    'campaign' => $campaign !== '' ? $campaign : null,
                    'orders' => $orders->count(),
                    'paid_orders' => $paid->count(),
                    'conversion_rate' => round($paid->count() / $orders->count() * 100, 1),
                    'revenue' => Money::of((int) $paid->sum('amount'), $currency),
                ];
            })
            ->sortByDesc(fn (array $row): int => $row['revenue']->amount)
            ->values()
            ->all();
    }

    /**
     * Where an order came from: its campaign source, the site that sent the buyer, or "direct" when neither is known.
     */
    public static function sourceOf(Order $order): string
    {
        return $order->attribution['source'] ?? $order->attribution['referrer'] ?? 'direct';
    }
}

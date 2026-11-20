<?php

namespace App\Reports;

use App\Enums\CheckoutStep;
use App\Enums\OrderStatus;
use App\Models\CheckoutEvent;
use App\Models\Order;
use App\Models\TrackedLink;
use App\Support\Money;
use App\Support\TrafficSource;
use Illuminate\Support\Collection;

/**
 * Where visitors and buyers come from: for each place, and each tracked link or campaign within it, how many people
 * looked at the ticket page, how many started the form, and the orders and money that followed.
 *
 * Visitors are counted once each, by where their first look at the ticket page in the period came from. Orders are
 * credited to where their buyer came from when they placed them.
 */
final class SalesBySource
{
    /**
     * @return list<array{source: string, detail: ?string, visitors: int, started: int, orders: int, paid_orders: int, conversion_rate: ?float, revenue: Money}>
     */
    public static function for(ReportPeriod $period, string $currency = 'NGN'): array
    {
        $linkNames = TrackedLink::query()->pluck('name', 'slug');
        $rows = [];

        $row = function (array $attribution) use (&$rows, $linkNames): string {
            $source = TrafficSource::label(TrafficSource::of($attribution));
            $detail = $linkNames->get($attribution['content'] ?? '') ?? $attribution['campaign'] ?? null;
            $key = $source.'|'.$detail;

            $rows[$key] ??= [
                'source' => $source,
                'detail' => $detail,
                'visitors' => 0,
                'started' => 0,
                'orders' => 0,
                'paid_orders' => 0,
                'conversion_rate' => null,
                'revenue' => 0,
            ];

            return $key;
        };

        $startedForm = $period->apply(CheckoutEvent::query(), 'occurred_at')
            ->where('step', CheckoutStep::FormStarted)
            ->whereNotNull('visitor_id')
            ->distinct()
            ->pluck('visitor_id')
            ->flip();

        self::firstViews($period)->each(function (CheckoutEvent $view) use ($row, &$rows, $startedForm): void {
            $key = $row([
                'source' => $view->properties['source'] ?? null,
                'content' => $view->properties['link'] ?? null,
                'campaign' => $view->properties['campaign'] ?? null,
            ]);

            $rows[$key]['visitors']++;
            $rows[$key]['started'] += (int) $startedForm->has($view->visitor_id);
        });

        $period->apply(Order::query()->where('currency', $currency)->where('status', '!=', OrderStatus::TestPayment), 'created_at')
            ->get(['id', 'status', 'amount', 'attribution'])
            ->each(function (Order $order) use ($row, &$rows): void {
                $key = $row($order->attribution ?? []);

                $rows[$key]['orders']++;

                if ($order->status === OrderStatus::Paid) {
                    $rows[$key]['paid_orders']++;
                    $rows[$key]['revenue'] += $order->amount;
                }
            });

        return collect($rows)
            ->map(fn (array $source): array => [
                ...$source,
                'conversion_rate' => $source['visitors'] > 0 ? round($source['paid_orders'] / $source['visitors'] * 100, 1) : null,
                'revenue' => Money::of($source['revenue'], $currency),
            ])
            ->sortBy([
                fn (array $a, array $b): int => $b['revenue']->amount <=> $a['revenue']->amount,
                fn (array $a, array $b): int => $b['visitors'] <=> $a['visitors'],
            ])
            ->values()
            ->all();
    }

    /**
     * Where an order came from, as reports and exports name it.
     */
    public static function sourceOf(Order $order): string
    {
        return TrafficSource::label(TrafficSource::of($order->attribution));
    }

    /**
     * Each visitor's first look at the ticket page in the period.
     *
     * @return Collection<int, CheckoutEvent>
     */
    private static function firstViews(ReportPeriod $period): Collection
    {
        return $period->apply(CheckoutEvent::query(), 'occurred_at')
            ->where('step', CheckoutStep::TicketPageViewed)
            ->whereNotNull('visitor_id')
            ->orderBy('occurred_at')
            ->orderBy('id')
            ->get(['id', 'visitor_id', 'properties'])
            ->unique('visitor_id');
    }
}

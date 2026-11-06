<?php

namespace App\Filament\Widgets;

use App\Enums\OrderStatus;
use App\Filament\Widgets\Concerns\ReadsReportPeriod;
use App\Models\Order;
use App\Reports\ReportPeriod;
use Carbon\CarbonImmutable;
use Carbon\CarbonPeriod;
use Filament\Widgets\ChartWidget;

class SalesChart extends ChartWidget
{
    use ReadsReportPeriod;

    protected static ?int $sort = 2;

    protected int|string|array $columnSpan = 'full';

    protected ?string $heading = 'Tickets sold per day';

    protected ?string $maxHeight = '260px';

    protected ?string $pollingInterval = null;

    protected function getData(): array
    {
        $period = $this->reportPeriod();
        $orders = $period->apply(Order::query()->where('status', OrderStatus::Paid), 'paid_at')->get(['paid_at', 'quantity']);
        $byDay = $orders->groupBy(fn (Order $order): string => $order->paid_at->timezone(ReportPeriod::Timezone)->toDateString())
            ->map(fn ($day): int => (int) $day->sum('quantity'));

        $from = $period->from?->timezone(ReportPeriod::Timezone) ?? ($orders->min('paid_at')?->timezone(ReportPeriod::Timezone)->toImmutable() ?? CarbonImmutable::now(ReportPeriod::Timezone));
        $until = $period->until?->timezone(ReportPeriod::Timezone) ?? CarbonImmutable::now(ReportPeriod::Timezone);
        $days = collect(CarbonPeriod::create($from->startOfDay(), '1 day', $until->startOfDay()))->map(fn ($day): string => $day->toDateString());

        return [
            'datasets' => [[
                'label' => 'Tickets sold',
                'data' => $days->map(fn (string $day): int => $byDay[$day] ?? 0)->all(),
                'backgroundColor' => 'rgba(255, 176, 0, .35)',
                'borderColor' => '#FFB000',
                'fill' => true,
            ]],
            'labels' => $days->map(fn (string $day): string => CarbonImmutable::parse($day)->format('j M'))->all(),
        ];
    }

    protected function getType(): string
    {
        return 'line';
    }
}

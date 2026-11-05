<?php

namespace App\Filament\Widgets;

use App\Enums\PaymentStatus;
use App\Filament\Widgets\Concerns\ReadsReportPeriod;
use App\Models\Order;
use App\Models\Payment;
use App\Models\TicketType;
use App\Models\WebhookCall;
use App\Reports\SalesSummary;
use Filament\Widgets\StatsOverviewWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;

class SalesOverview extends StatsOverviewWidget
{
    use ReadsReportPeriod;

    protected static ?int $sort = 1;

    protected ?string $pollingInterval = '60s';

    protected function getStats(): array
    {
        $summary = SalesSummary::for($this->reportPeriod());
        $ticketsLeft = TicketType::query()->onSale()->get()->map(fn (TicketType $ticketType): ?int => $ticketType->ticketsLeft());
        $needsAttention = WebhookCall::query()->whereNotNull('failed_at')->whereNull('processed_at')->count()
            + Payment::query()->where('status', PaymentStatus::Underpaid)->count()
            + Order::query()->whereNotNull('review_note')->count();

        return [
            Stat::make('Revenue', $summary['revenue']->format())
                ->description($summary['paid_orders'].' paid '.str('order')->plural($summary['paid_orders']).' · '.$summary['fees']->format().' in fees')
                ->color('success'),
            Stat::make('Tickets sold', number_format($summary['tickets_sold']))
                ->description($ticketsLeft->isEmpty() ? 'Nothing on sale' : ($ticketsLeft->contains(null) ? 'No limit set' : number_format($ticketsLeft->sum()).' left')),
            Stat::make('Visitors who bought', $summary['conversion_rate'] === null ? '–' : $summary['conversion_rate'].'%')
                ->description(number_format($summary['visitors']).' '.str('visitor')->plural($summary['visitors']).' · average order '.($summary['average_order']?->format() ?? '–')),
            Stat::make('Abandoned orders', number_format($summary['abandoned_orders']))
                ->description('Left unpaid when their hold ran out')
                ->color($summary['abandoned_orders'] > 0 ? 'warning' : 'gray'),
            Stat::make('Needs attention', number_format($needsAttention))
                ->description('Failed webhooks, short payments and orders flagged for a look')
                ->color($needsAttention > 0 ? 'danger' : 'gray'),
        ];
    }
}

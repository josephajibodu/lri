<?php

namespace App\Filament\Widgets\Concerns;

use App\Enums\AdminPermission;
use App\Reports\ReportPeriod;
use Carbon\CarbonImmutable;
use Filament\Widgets\Concerns\InteractsWithPageFilters;

/**
 * Read the dashboard's period filter, defaulting to the last 30 days. Only people allowed the sales dashboard see
 * these widgets.
 */
trait ReadsReportPeriod
{
    use InteractsWithPageFilters;

    public static function canView(): bool
    {
        return auth()->user()?->can(AdminPermission::ViewDashboard->value) ?? false;
    }

    protected function reportPeriod(): ReportPeriod
    {
        $range = $this->pageFilters['range'] ?? '30';
        $today = CarbonImmutable::now(ReportPeriod::Timezone)->toDateString();

        return match ($range) {
            'all' => new ReportPeriod,
            'custom' => ReportPeriod::fromDates($this->pageFilters['startDate'] ?? null, $this->pageFilters['endDate'] ?? null),
            'today' => ReportPeriod::fromDates($today, $today),
            default => ReportPeriod::fromDates(CarbonImmutable::now(ReportPeriod::Timezone)->subDays(max(1, (int) $range) - 1)->toDateString(), $today),
        };
    }
}

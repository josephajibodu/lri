<?php

namespace App\Filament\Widgets\Concerns;

use App\Reports\ReportPeriod;
use Carbon\CarbonImmutable;
use Filament\Widgets\Concerns\InteractsWithPageFilters;

/**
 * Read the dashboard's period filter, defaulting to the last 30 days.
 */
trait ReadsReportPeriod
{
    use InteractsWithPageFilters;

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

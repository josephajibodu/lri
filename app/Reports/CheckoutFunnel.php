<?php

namespace App\Reports;

use App\Enums\CheckoutStep;
use App\Models\CheckoutEvent;

/**
 * How many people reached each step from the ticket page to paying, so the biggest drop shows where to look.
 * People are counted once per step by their visitor id.
 */
final class CheckoutFunnel
{
    /**
     * @return list<array{step: CheckoutStep, label: string, people: int, of_start: ?float, of_previous: ?float}>
     */
    public static function for(ReportPeriod $period): array
    {
        $counts = $period->apply(CheckoutEvent::query(), 'occurred_at')
            ->whereIn('step', CheckoutStep::funnel())
            ->whereNotNull('visitor_id')
            ->selectRaw('step, count(distinct visitor_id) as people')
            ->groupBy('step')
            ->toBase()
            ->pluck('people', 'step');

        $start = null;
        $previous = null;
        $rows = [];

        foreach (CheckoutStep::funnel() as $step) {
            $people = (int) ($counts[$step->value] ?? 0);
            $start ??= $people;

            $rows[] = [
                'step' => $step,
                'label' => $step->label(),
                'people' => $people,
                'of_start' => $start > 0 ? round($people / $start * 100, 1) : null,
                'of_previous' => $previous !== null && $previous > 0 ? round($people / $previous * 100, 1) : null,
            ];

            $previous = $people;
        }

        return $rows;
    }
}

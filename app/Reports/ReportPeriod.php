<?php

namespace App\Reports;

use Carbon\CarbonImmutable;
use Illuminate\Contracts\Database\Query\Builder;

/**
 * The span of days a report covers, read in Ibadan time. Either end can be open.
 */
final readonly class ReportPeriod
{
    public const Timezone = 'Africa/Lagos';

    public function __construct(
        public ?CarbonImmutable $from = null,
        public ?CarbonImmutable $until = null,
    ) {}

    /**
     * A period from the dashboard's date pickers (Y-m-d strings), covering whole days.
     */
    public static function fromDates(?string $startDate, ?string $endDate): self
    {
        return new self(
            filled($startDate) ? CarbonImmutable::parse($startDate, self::Timezone)->startOfDay()->utc() : null,
            filled($endDate) ? CarbonImmutable::parse($endDate, self::Timezone)->endOfDay()->utc() : null,
        );
    }

    /**
     * Limit a query to rows whose column falls inside the period.
     *
     * @template TBuilder of Builder
     *
     * @param  TBuilder  $query
     * @return TBuilder
     */
    public function apply(Builder $query, string $column): Builder
    {
        return $query
            ->when($this->from, fn (Builder $query) => $query->where($column, '>=', $this->from))
            ->when($this->until, fn (Builder $query) => $query->where($column, '<=', $this->until));
    }
}

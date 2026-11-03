<?php

namespace App\Filament\Widgets;

use App\Filament\Widgets\Concerns\ReadsReportPeriod;
use App\Reports\PaymentMethodPerformance;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Filament\Widgets\TableWidget;

class PaymentMethodsTable extends TableWidget
{
    use ReadsReportPeriod;

    protected static ?int $sort = 4;

    protected int|string|array $columnSpan = 'full';

    protected static ?string $heading = 'Ways to pay';

    public function table(Table $table): Table
    {
        return $table
            ->description('Every payment attempt by method. The success rate leaves out attempts still in progress.')
            ->records(fn (): array => collect(PaymentMethodPerformance::for($this->reportPeriod()))
                ->mapWithKeys(fn (array $row): array => [$row['method']->value ?? 'unknown' => [
                    'label' => $row['label'],
                    'attempts' => number_format($row['attempts']),
                    'succeeded' => number_format($row['succeeded']),
                    'failed' => number_format($row['failed']),
                    'unfinished' => number_format($row['unfinished']),
                    'success_rate' => $row['success_rate'] === null ? '–' : $row['success_rate'].'%',
                    'revenue' => $row['revenue']->format(),
                    'minutes_to_pay' => $row['median_minutes_to_pay'] === null ? '–' : $row['median_minutes_to_pay'].' min',
                    'top_failure' => $row['top_failure'] ? $row['top_failure']->label().' ('.$row['top_failure_count'].')' : '–',
                ]])
                ->all())
            ->columns([
                TextColumn::make('label')->label('Method'),
                TextColumn::make('attempts')->alignEnd(),
                TextColumn::make('succeeded')->label('Paid')->alignEnd(),
                TextColumn::make('failed')->alignEnd(),
                TextColumn::make('unfinished')->label('Left or expired')->alignEnd(),
                TextColumn::make('success_rate')->label('Success rate')->alignEnd()->weight('bold'),
                TextColumn::make('revenue')->alignEnd(),
                TextColumn::make('minutes_to_pay')->label('Typical time to pay')->alignEnd(),
                TextColumn::make('top_failure')->label('Most common failure'),
            ])
            ->emptyStateHeading('No payment attempts in this period')
            ->paginated(false);
    }
}

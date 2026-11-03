<?php

namespace App\Filament\Widgets;

use App\Filament\Widgets\Concerns\ReadsReportPeriod;
use App\Reports\CheckoutFunnel;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Filament\Widgets\TableWidget;

class CheckoutFunnelTable extends TableWidget
{
    use ReadsReportPeriod;

    protected static ?int $sort = 3;

    protected int|string|array $columnSpan = 'full';

    protected static ?string $heading = 'Checkout funnel';

    public function table(Table $table): Table
    {
        return $table
            ->description('People who reached each step, counted once each. The biggest drop is where to look.')
            ->records(fn (): array => collect(CheckoutFunnel::for($this->reportPeriod()))
                ->mapWithKeys(fn (array $row, int $index): array => [$index + 1 => [
                    'label' => $row['label'],
                    'people' => number_format($row['people']),
                    'of_start' => $row['of_start'] === null ? '–' : $row['of_start'].'%',
                    'of_previous' => $row['of_previous'] === null ? '–' : $row['of_previous'].'%',
                ]])
                ->all())
            ->columns([
                TextColumn::make('label')->label('Step'),
                TextColumn::make('people')->label('People')->alignEnd(),
                TextColumn::make('of_previous')->label('Of the step before')->alignEnd(),
                TextColumn::make('of_start')->label('Of everyone')->alignEnd(),
            ])
            ->paginated(false);
    }
}

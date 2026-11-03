<?php

namespace App\Filament\Widgets;

use App\Filament\Widgets\Concerns\ReadsReportPeriod;
use App\Reports\SalesBySource;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Filament\Widgets\TableWidget;

class SalesBySourceTable extends TableWidget
{
    use ReadsReportPeriod;

    protected static ?int $sort = 5;

    protected int|string|array $columnSpan = 'full';

    protected static ?string $heading = 'Where buyers come from';

    public function table(Table $table): Table
    {
        return $table
            ->description('Orders by the campaign tag (utm_source) or site that sent the buyer. Tag your links to see each post or broadcast here.')
            ->records(fn (): array => collect(SalesBySource::for($this->reportPeriod()))
                ->mapWithKeys(fn (array $row, int $index): array => [$index + 1 => [
                    'source' => $row['source'],
                    'campaign' => $row['campaign'] ?? '–',
                    'orders' => number_format($row['orders']),
                    'paid_orders' => number_format($row['paid_orders']),
                    'conversion_rate' => $row['conversion_rate'].'%',
                    'revenue' => $row['revenue']->format(),
                ]])
                ->all())
            ->columns([
                TextColumn::make('source'),
                TextColumn::make('campaign'),
                TextColumn::make('orders')->label('Orders started')->alignEnd(),
                TextColumn::make('paid_orders')->label('Paid')->alignEnd(),
                TextColumn::make('conversion_rate')->label('Paid of started')->alignEnd(),
                TextColumn::make('revenue')->alignEnd(),
            ])
            ->emptyStateHeading('No orders in this period')
            ->paginated(false);
    }
}

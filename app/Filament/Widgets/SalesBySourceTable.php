<?php

namespace App\Filament\Widgets;

use App\Filament\Widgets\Concerns\ReadsReportPeriod;
use App\Reports\SalesBySource;
use Filament\Support\Enums\FontWeight;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Filament\Widgets\TableWidget;

class SalesBySourceTable extends TableWidget
{
    use ReadsReportPeriod;

    protected static ?int $sort = 5;

    protected int|string|array $columnSpan = 'full';

    protected static ?string $heading = 'Where visitors come from';

    public function table(Table $table): Table
    {
        return $table
            ->description('Each visitor counted once, by the tracked link, app or site that sent them to the ticket page. WhatsApp, QR codes and typed addresses don’t say where they came from, so they show as “Direct or unknown” unless people used a tracked link: make one under Marketing for each place you share the tickets.')
            ->records(fn (): array => collect(SalesBySource::for($this->reportPeriod()))
                ->mapWithKeys(fn (array $row, int $index): array => [$index + 1 => [
                    'source' => $row['source'],
                    'detail' => $row['detail'] ?? '–',
                    'visitors' => number_format($row['visitors']),
                    'started' => number_format($row['started']),
                    'paid_orders' => number_format($row['paid_orders']),
                    'revenue' => $row['revenue']->format(),
                    'conversion_rate' => $row['conversion_rate'] === null ? '–' : $row['conversion_rate'].'%',
                ]])
                ->all())
            ->columns([
                TextColumn::make('source')->weight(FontWeight::SemiBold),
                TextColumn::make('detail')->label('Link or campaign'),
                TextColumn::make('visitors')->label('Visitors')->alignEnd(),
                TextColumn::make('started')->label('Started the form')->alignEnd(),
                TextColumn::make('paid_orders')->label('Paid orders')->alignEnd(),
                TextColumn::make('revenue')->alignEnd(),
                TextColumn::make('conversion_rate')->label('Paid per visitor')->alignEnd(),
            ])
            ->emptyStateHeading('No visitors in this period')
            ->paginated(false);
    }
}

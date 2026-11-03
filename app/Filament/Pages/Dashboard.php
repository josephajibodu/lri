<?php

namespace App\Filament\Pages;

use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Select;
use Filament\Pages\Dashboard as BaseDashboard;
use Filament\Pages\Dashboard\Concerns\HasFiltersForm;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Schema;

/**
 * Ticket sales at a glance: money in, where buyers drop off, which ways to pay work, and where buyers come from.
 */
class Dashboard extends BaseDashboard
{
    use HasFiltersForm;

    public function filtersForm(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make()
                    ->schema([
                        Select::make('range')
                            ->label('Period')
                            ->options([
                                'today' => 'Today',
                                '7' => 'Last 7 days',
                                '30' => 'Last 30 days',
                                '90' => 'Last 90 days',
                                'all' => 'All time',
                                'custom' => 'Pick dates',
                            ])
                            ->default('30')
                            ->selectablePlaceholder(false)
                            ->live(),
                        DatePicker::make('startDate')
                            ->label('From')
                            ->visible(fn (Get $get): bool => $get('range') === 'custom'),
                        DatePicker::make('endDate')
                            ->label('To')
                            ->visible(fn (Get $get): bool => $get('range') === 'custom'),
                    ])
                    ->columns(3)
                    ->columnSpanFull(),
            ]);
    }
}

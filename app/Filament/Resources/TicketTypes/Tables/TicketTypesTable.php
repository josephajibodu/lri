<?php

namespace App\Filament\Resources\TicketTypes\Tables;

use App\Enums\OrderStatus;
use App\Models\TicketType;
use App\Support\AdminActivity;
use Filament\Actions\EditAction;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Columns\ToggleColumn;
use Filament\Tables\Table;

class TicketTypesTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->modifyQueryUsing(fn ($query) => $query->withSum(['orders as tickets_sold' => fn ($query) => $query->where('status', OrderStatus::Paid)], 'quantity'))
            ->reorderable('sort_order')
            ->afterReordering(fn () => AdminActivity::record('Changed the order of the ticket types'))
            ->defaultSort('sort_order')
            ->columns([
                TextColumn::make('name')->description(fn (TicketType $ticketType): string => $ticketType->slug),
                TextColumn::make('price')->formatStateUsing(fn (TicketType $ticketType): string => $ticketType->unitPrice()->format())->alignEnd(),
                TextColumn::make('tickets_sold')->label('Sold')->numeric()->default(0)->alignEnd(),
                TextColumn::make('tickets_left')
                    ->label('Left')
                    ->state(fn (TicketType $ticketType): string => $ticketType->ticketsLeft() === null ? 'No limit' : number_format($ticketType->ticketsLeft()))
                    ->alignEnd(),
                ToggleColumn::make('is_active')->label('On sale'),
                TextColumn::make('sales_end_at')->label('Sales close')->dateTime('j M, g:i A', 'Africa/Lagos')->placeholder('–'),
            ])
            ->recordActions([
                EditAction::make(),
            ]);
    }
}

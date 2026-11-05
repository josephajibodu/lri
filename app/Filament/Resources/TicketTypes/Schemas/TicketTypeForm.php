<?php

namespace App\Filament\Resources\TicketTypes\Schemas;

use Filament\Forms\Components\DateTimePicker;
use Filament\Forms\Components\Hidden;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Illuminate\Support\Str;

class TicketTypeForm
{
    /**
     * There's only one event and one currency so far, so neither is a choice worth showing. They're still checked, so
     * the form can't save any other.
     */
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->columns(1)
            ->components([
                Hidden::make('event')->default('disco-run')->required()->in(['disco-run']),
                Hidden::make('currency')->default('NGN')->required()->in(['NGN']),
                Section::make('Ticket')
                    ->description('What buyers see when they choose a ticket.')
                    ->aside()
                    ->schema([
                        TextInput::make('name')
                            ->required()
                            ->markAsRequired(false)
                            ->maxLength(120)
                            ->live(onBlur: true)
                            ->afterStateUpdated(fn (?string $state, callable $set, string $operation) => $operation === 'create' ? $set('slug', Str::slug((string) $state)) : null),
                        TextInput::make('description')
                            ->hint('Optional')
                            ->maxLength(255)
                            ->placeholder('Includes silent disco headphones'),
                        TextInput::make('slug')
                            ->required()
                            ->markAsRequired(false)
                            ->maxLength(120)
                            ->alphaDash()
                            ->unique(ignoreRecord: true)
                            ->helperText('Used in the checkout form. Don’t change it once tickets are on sale.'),
                    ]),
                Section::make('Price and limits')
                    ->description('Orders being paid for keep their price. One that lapses and is picked up again pays the new price.')
                    ->aside()
                    ->schema([
                        TextInput::make('price')
                            ->label('Price')
                            ->prefix('₦')
                            ->numeric()
                            ->minValue(100)
                            ->required()
                            ->markAsRequired(false)
                            ->formatStateUsing(fn (?int $state): ?string => $state === null ? null : rtrim(rtrim(number_format($state / 100, 2, '.', ''), '0'), '.'))
                            ->dehydrateStateUsing(fn (string $state): int => (int) round(((float) $state) * 100)),
                        TextInput::make('capacity')
                            ->label('Tickets available')
                            ->numeric()
                            ->minValue(0)
                            ->placeholder('No limit'),
                        TextInput::make('max_per_order')
                            ->label('Most in one order')
                            ->numeric()
                            ->minValue(1)
                            ->maxValue(50)
                            ->default(10)
                            ->required()
                            ->markAsRequired(false),
                    ])
                    ->columns(3),
                Section::make('Sales')
                    ->description('Leave the dates empty to open straight away and stay open until switched off.')
                    ->aside()
                    ->schema([
                        Toggle::make('is_active')->label('On sale')->default(true)->columnSpanFull(),
                        DateTimePicker::make('sales_start_at')->label('Sales open')->timezone('Africa/Lagos'),
                        DateTimePicker::make('sales_end_at')->label('Sales close')->timezone('Africa/Lagos'),
                    ])
                    ->columns(2),
            ]);
    }
}

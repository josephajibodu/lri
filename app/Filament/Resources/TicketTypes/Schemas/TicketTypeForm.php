<?php

namespace App\Filament\Resources\TicketTypes\Schemas;

use Filament\Forms\Components\DateTimePicker;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Illuminate\Support\Str;

class TicketTypeForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make('Ticket')
                    ->schema([
                        TextInput::make('name')
                            ->required()
                            ->maxLength(120)
                            ->live(onBlur: true)
                            ->afterStateUpdated(fn (?string $state, callable $set, string $operation) => $operation === 'create' ? $set('slug', Str::slug((string) $state)) : null),
                        TextInput::make('slug')
                            ->required()
                            ->maxLength(120)
                            ->alphaDash()
                            ->unique(ignoreRecord: true)
                            ->helperText('Used in the checkout form. Don’t change it once tickets are on sale.'),
                        Select::make('event')
                            ->options(['disco-run' => 'Silent Disco Run'])
                            ->default('disco-run')
                            ->required()
                            ->helperText('The event page that sells this ticket. Its date and venue come from config/lri.php.'),
                        TextInput::make('description')
                            ->maxLength(255)
                            ->placeholder('Includes silent disco headphones'),
                    ])
                    ->columns(2),
                Section::make('Price and limits')
                    ->schema([
                        TextInput::make('price')
                            ->label('Price')
                            ->prefix('₦')
                            ->numeric()
                            ->minValue(100)
                            ->required()
                            ->formatStateUsing(fn (?int $state): ?string => $state === null ? null : rtrim(rtrim(number_format($state / 100, 2, '.', ''), '0'), '.'))
                            ->dehydrateStateUsing(fn (string $state): int => (int) round(((float) $state) * 100))
                            ->helperText('In naira. Orders keep the price they were placed at, so changing it doesn’t affect them.'),
                        Select::make('currency')
                            ->options(['NGN' => 'Naira (NGN)'])
                            ->default('NGN')
                            ->required(),
                        TextInput::make('capacity')
                            ->label('Tickets available')
                            ->numeric()
                            ->minValue(0)
                            ->helperText('Leave empty for no limit, such as when the number of headsets isn’t fixed yet.'),
                        TextInput::make('max_per_order')
                            ->label('Most tickets in one order')
                            ->numeric()
                            ->minValue(1)
                            ->maxValue(50)
                            ->default(10)
                            ->required(),
                    ])
                    ->columns(2),
                Section::make('Sales')
                    ->schema([
                        Toggle::make('is_active')->label('On sale')->default(true),
                        DateTimePicker::make('sales_start_at')->label('Sales open')->timezone('Africa/Lagos')->helperText('Empty means straight away.'),
                        DateTimePicker::make('sales_end_at')->label('Sales close')->timezone('Africa/Lagos')->helperText('Empty means until switched off.'),
                    ])
                    ->columns(3),
            ]);
    }
}

<?php

namespace App\Filament\Resources\Orders\RelationManagers;

use App\Enums\PaymentFailureReason;
use App\Models\CheckoutEvent;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

/**
 * The buyer's journey for this order, step by step, to see what went wrong when they didn't pay.
 */
class CheckoutEventsRelationManager extends RelationManager
{
    protected static string $relationship = 'checkoutEvents';

    protected static ?string $title = 'Journey';

    public function table(Table $table): Table
    {
        return $table
            ->recordTitleAttribute('step')
            ->columns([
                TextColumn::make('occurred_at')->label('When')->dateTime('j M, g:i:s A', 'Africa/Lagos'),
                TextColumn::make('step')->badge(),
                TextColumn::make('method')->placeholder('–'),
                TextColumn::make('properties')
                    ->label('Details')
                    ->state(fn (CheckoutEvent $event): string => collect($event->properties ?? [])
                        ->map(fn (mixed $value, string $key): string => str_replace('_', ' ', $key).': '.match (true) {
                            is_bool($value) => $value ? 'yes' : 'no',
                            is_array($value) => implode(', ', $value),
                            $key === 'reason' => PaymentFailureReason::tryFrom((string) $value)?->label() ?? str_replace('_', ' ', (string) $value),
                            default => $value,
                        })
                        ->implode(' · '))
                    ->placeholder('–')
                    ->wrap(),
            ])
            ->paginated([25, 50, 100]);
    }
}

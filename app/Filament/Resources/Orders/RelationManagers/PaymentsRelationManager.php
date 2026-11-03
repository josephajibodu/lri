<?php

namespace App\Filament\Resources\Orders\RelationManagers;

use App\Models\Payment;
use App\Support\Money;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

/**
 * Every attempt to pay for the order, failed ones included.
 */
class PaymentsRelationManager extends RelationManager
{
    protected static string $relationship = 'payments';

    protected static ?string $title = 'Payment attempts';

    public function table(Table $table): Table
    {
        return $table
            ->recordTitleAttribute('reference')
            ->defaultSort('created_at', 'desc')
            ->columns([
                TextColumn::make('reference')->copyable()->fontFamily('mono'),
                TextColumn::make('method')->badge()->color('gray')->placeholder('–'),
                TextColumn::make('status')->badge(),
                TextColumn::make('failure_reason')
                    ->label('Why it failed')
                    ->description(fn (Payment $payment): ?string => $payment->failure_message)
                    ->placeholder('–')
                    ->wrap(),
                TextColumn::make('amount')->formatStateUsing(fn (Payment $payment): string => $payment->money()->format())->alignEnd(),
                TextColumn::make('fee')->formatStateUsing(fn (Payment $payment): string => $payment->fee === null ? '–' : Money::of($payment->fee, $payment->currency)->format())->alignEnd()->placeholder('–'),
                TextColumn::make('provider')->description(fn (Payment $payment): ?string => $payment->provider_checkout_id)->toggleable(),
                TextColumn::make('provider_status')->label('Provider says')->placeholder('–')->toggleable(isToggledHiddenByDefault: true),
                TextColumn::make('created_at')->label('Started')->dateTime('j M, g:i A', 'Africa/Lagos'),
                TextColumn::make('paid_at')->label('Paid')->dateTime('j M, g:i A', 'Africa/Lagos')->placeholder('–'),
                TextColumn::make('last_checked_at')->label('Last checked')->since()->placeholder('Never')->toggleable(isToggledHiddenByDefault: true),
            ]);
    }
}

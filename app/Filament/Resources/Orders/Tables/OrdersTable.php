<?php

namespace App\Filament\Resources\Orders\Tables;

use App\Enums\OrderStatus;
use App\Enums\PaymentMethod;
use App\Models\Order;
use App\Reports\SalesBySource;
use Filament\Actions\ViewAction;
use Filament\Forms\Components\DatePicker;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\Filter;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

class OrdersTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->modifyQueryUsing(fn (Builder $query) => $query->with(['ticketType', 'latestPayment', 'coupon']))
            ->defaultSort('created_at', 'desc')
            ->columns([
                TextColumn::make('reference')
                    ->searchable()
                    ->copyable()
                    ->weight('bold'),
                TextColumn::make('buyer_name')
                    ->label('Buyer')
                    ->description(fn (Order $order): string => $order->buyer_email)
                    ->searchable(['buyer_name', 'buyer_email', 'buyer_phone']),
                TextColumn::make('quantity')
                    ->label('Tickets')
                    ->alignEnd(),
                TextColumn::make('amount')
                    ->label('Total')
                    ->formatStateUsing(fn (Order $order): string => $order->total()->format())
                    ->alignEnd()
                    ->sortable(),
                TextColumn::make('coupon.code')
                    ->label('Coupon')
                    ->description(fn (Order $order): ?string => $order->coupon ? '−'.$order->discount()->format() : null)
                    ->placeholder('–')
                    ->toggleable(),
                TextColumn::make('status')
                    ->badge(),
                TextColumn::make('latestPayment.method')
                    ->label('Method')
                    ->badge()
                    ->color('gray'),
                TextColumn::make('latestPayment.failure_reason')
                    ->label('Last failure')
                    ->placeholder('–')
                    ->toggleable(),
                TextColumn::make('source')
                    ->state(fn (Order $order): string => SalesBySource::sourceOf($order))
                    ->toggleable(),
                TextColumn::make('created_at')
                    ->label('Placed')
                    ->since()
                    ->dateTimeTooltip('j M Y, g:i A', 'Africa/Lagos')
                    ->sortable(),
                TextColumn::make('paid_at')
                    ->label('Paid')
                    ->dateTime('j M, g:i A', 'Africa/Lagos')
                    ->placeholder('–')
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                SelectFilter::make('status')
                    ->options(OrderStatus::class)
                    ->multiple(),
                SelectFilter::make('method')
                    ->label('Method tried')
                    ->options(PaymentMethod::class)
                    ->query(fn (Builder $query, array $data): Builder => $query->when(
                        $data['value'] ?? null,
                        fn (Builder $query, string $method) => $query->whereHas('payments', fn (Builder $query) => $query->where('method', $method)),
                    )),
                SelectFilter::make('ticket_type_id')
                    ->label('Ticket')
                    ->relationship('ticketType', 'name'),
                SelectFilter::make('coupon_id')
                    ->label('Coupon')
                    ->relationship('coupon', 'code')
                    ->searchable(),
                Filter::make('placed')
                    ->schema([
                        DatePicker::make('from')->label('Placed from'),
                        DatePicker::make('until')->label('Placed until'),
                    ])
                    ->query(fn (Builder $query, array $data): Builder => $query
                        ->when($data['from'] ?? null, fn (Builder $query, string $date) => $query->whereDate('created_at', '>=', $date))
                        ->when($data['until'] ?? null, fn (Builder $query, string $date) => $query->whereDate('created_at', '<=', $date))),
            ])
            ->recordActions([
                ViewAction::make(),
            ]);
    }
}

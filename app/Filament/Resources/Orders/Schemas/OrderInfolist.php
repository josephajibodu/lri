<?php

namespace App\Filament\Resources\Orders\Schemas;

use App\Models\Order;
use App\Reports\SalesBySource;
use Filament\Infolists\Components\IconEntry;
use Filament\Infolists\Components\KeyValueEntry;
use Filament\Infolists\Components\RepeatableEntry;
use Filament\Infolists\Components\TextEntry;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;

class OrderInfolist
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make('Order')
                    ->schema([
                        TextEntry::make('reference')->copyable()->weight('bold'),
                        TextEntry::make('status')->badge(),
                        TextEntry::make('ticketType.name')->label('Ticket'),
                        TextEntry::make('amount')
                            ->label('Total')
                            ->formatStateUsing(fn (Order $order): string => $order->quantity.' × '.$order->unitPrice()->format().' = '.$order->total()->format()),
                        TextEntry::make('created_at')->label('Placed')->dateTime('j M Y, g:i A', 'Africa/Lagos'),
                        TextEntry::make('paid_at')->label('Paid')->dateTime('j M Y, g:i A', 'Africa/Lagos')->placeholder('Not paid'),
                        TextEntry::make('expires_at')->label('Tickets held until')->dateTime('j M Y, g:i A', 'Africa/Lagos')->placeholder('–'),
                        TextEntry::make('abandoned_at')->label('Abandoned')->dateTime('j M Y, g:i A', 'Africa/Lagos')->placeholder('–'),
                        TextEntry::make('tickets_emailed_at')->label('Tickets emailed')->dateTime('j M Y, g:i A', 'Africa/Lagos')->placeholder('Not yet'),
                    ])
                    ->columns(3)
                    ->columnSpanFull(),
                Section::make('Buyer')
                    ->schema([
                        TextEntry::make('buyer_name')->label('Name'),
                        TextEntry::make('buyer_email')->label('Email')->copyable(),
                        TextEntry::make('buyer_phone')->label('Phone')->copyable()->placeholder('Not given'),
                        IconEntry::make('marketing_consent')->label('Agreed to event emails')->boolean(),
                    ])
                    ->columns(2),
                Section::make('Where they came from')
                    ->schema([
                        TextEntry::make('source')->state(fn (Order $order): string => SalesBySource::sourceOf($order)),
                        KeyValueEntry::make('attribution')
                            ->label('Details')
                            ->keyLabel('Tag')
                            ->valueLabel('Value')
                            ->placeholder('Came straight to the site'),
                    ]),
                Section::make('Tickets')
                    ->schema([
                        RepeatableEntry::make('tickets')
                            ->hiddenLabel()
                            ->schema([
                                TextEntry::make('code')->copyable()->fontFamily('mono')->weight('bold'),
                                TextEntry::make('holder_name')->label('Holder')->placeholder('No name yet'),
                                TextEntry::make('holder_email')->label('Holder’s email')->placeholder('–'),
                                TextEntry::make('checked_in_at')->label('Headset collected')->dateTime('j M, g:i A', 'Africa/Lagos')->placeholder('Not yet'),
                            ])
                            ->columns(4)
                            ->placeholder('Issued once the order is paid'),
                    ])
                    ->columnSpanFull(),
            ]);
    }
}

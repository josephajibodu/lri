<?php

namespace App\Filament\Resources\TicketTypes;

use App\Filament\Resources\TicketTypes\Pages\CreateTicketType;
use App\Filament\Resources\TicketTypes\Pages\EditTicketType;
use App\Filament\Resources\TicketTypes\Pages\ListTicketTypes;
use App\Filament\Resources\TicketTypes\Schemas\TicketTypeForm;
use App\Filament\Resources\TicketTypes\Tables\TicketTypesTable;
use App\Models\TicketType;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;
use UnitEnum;

class TicketTypeResource extends Resource
{
    protected static ?string $model = TicketType::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedTicket;

    protected static string|UnitEnum|null $navigationGroup = 'Tickets';

    protected static ?int $navigationSort = 2;

    protected static ?string $recordTitleAttribute = 'name';

    public static function form(Schema $schema): Schema
    {
        return TicketTypeForm::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return TicketTypesTable::configure($table);
    }

    public static function getRelations(): array
    {
        return [
            //
        ];
    }

    public static function getPages(): array
    {
        return [
            'index' => ListTicketTypes::route('/'),
            'create' => CreateTicketType::route('/create'),
            'edit' => EditTicketType::route('/{record}/edit'),
        ];
    }
}

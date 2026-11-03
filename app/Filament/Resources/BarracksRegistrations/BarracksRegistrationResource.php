<?php

namespace App\Filament\Resources\BarracksRegistrations;

use App\Filament\Resources\BarracksRegistrations\Pages\ManageBarracksRegistrations;
use App\Models\BarracksRegistration;
use BackedEnum;
use Filament\Actions\Action;
use Filament\Actions\ViewAction;
use Filament\Infolists\Components\ImageEntry;
use Filament\Infolists\Components\TextEntry;
use Filament\Resources\Resource;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\ImageColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use UnitEnum;

/**
 * Everyone who filled in the Akobo Barracks permit form, so organisers can check them and print the forms to take to the barracks.
 */
class BarracksRegistrationResource extends Resource
{
    protected static ?string $model = BarracksRegistration::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedIdentification;

    protected static string|UnitEnum|null $navigationGroup = 'Club';

    protected static ?string $navigationLabel = 'Barracks permits';

    protected static ?string $modelLabel = 'barracks registration';

    protected static ?int $navigationSort = 2;

    protected static ?string $recordTitleAttribute = 'full_name';

    public static function getNavigationBadge(): ?string
    {
        return (string) BarracksRegistration::query()->count();
    }

    public static function infolist(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make('Passport photograph')
                    ->schema([
                        ImageEntry::make('passport_path')->hiddenLabel()->imageHeight(220),
                    ]),
                Section::make('Details')
                    ->schema([
                        TextEntry::make('full_name')->label('Full name')->weight('bold'),
                        TextEntry::make('phone')->copyable(),
                        TextEntry::make('occupation'),
                        TextEntry::make('state_of_origin')->label('State of origin'),
                        TextEntry::make('lga')->label('LGA'),
                        TextEntry::make('residential_address')->label('Residential address')->columnSpanFull(),
                        TextEntry::make('signature_name')->label('Signed as'),
                        TextEntry::make('declared_at')->label('Declared')->dateTime('j M Y, g:i A', 'Africa/Lagos'),
                    ])
                    ->columns(2),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->defaultSort('created_at')
            ->columns([
                ImageColumn::make('passport_path')->label('Passport')->circular(),
                TextColumn::make('full_name')->label('Name')->weight('bold')->searchable()->sortable(),
                TextColumn::make('phone')->copyable()->searchable(),
                TextColumn::make('occupation')->toggleable(),
                TextColumn::make('lga')
                    ->label('From')
                    ->formatStateUsing(fn (BarracksRegistration $registration): string => $registration->lga.', '.$registration->state_of_origin)
                    ->toggleable(),
                TextColumn::make('residential_address')->label('Address')->limit(40)->wrap()->toggleable(isToggledHiddenByDefault: true),
                TextColumn::make('created_at')->label('Registered')->dateTime('j M Y, g:i A', 'Africa/Lagos')->sortable(),
            ])
            ->filters([
                SelectFilter::make('state_of_origin')
                    ->label('State of origin')
                    ->options(fn (): array => BarracksRegistration::query()->orderBy('state_of_origin')->distinct()->pluck('state_of_origin', 'state_of_origin')->all()),
            ])
            ->emptyStateHeading('Nobody has registered yet')
            ->emptyStateDescription('Share the registration form in the Akobo group so members can register.')
            ->recordActions([
                ViewAction::make(),
                Action::make('print')
                    ->label('Print')
                    ->icon(Heroicon::OutlinedPrinter)
                    ->color('gray')
                    ->url(fn (BarracksRegistration $registration): string => route('barracks.print', $registration), shouldOpenInNewTab: true),
            ]);
    }

    public static function canCreate(): bool
    {
        return false;
    }

    public static function getPages(): array
    {
        return [
            'index' => ManageBarracksRegistrations::route('/'),
        ];
    }
}

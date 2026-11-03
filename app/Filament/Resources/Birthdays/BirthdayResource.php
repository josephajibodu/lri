<?php

namespace App\Filament\Resources\Birthdays;

use App\Filament\Resources\Birthdays\Pages\ManageBirthdays;
use App\Models\Birthday;
use BackedEnum;
use Filament\Actions\ViewAction;
use Filament\Infolists\Components\IconEntry;
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
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Carbon;
use UnitEnum;

/**
 * Members' birthdays, a month at a time, so organisers can plan who to celebrate. Members add their own on the public share form.
 */
class BirthdayResource extends Resource
{
    protected static ?string $model = Birthday::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedCake;

    protected static string|UnitEnum|null $navigationGroup = 'Club';

    protected static ?int $navigationSort = 1;

    protected static ?string $recordTitleAttribute = 'full_name';

    public static function infolist(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make('Birthday')
                    ->schema([
                        TextEntry::make('birth_day')
                            ->label('Next celebrated')
                            ->state(fn (Birthday $birthday): string => self::celebrationDate($birthday)->format('l j F Y')),
                        TextEntry::make('turning')
                            ->label('Turning')
                            ->state(fn (Birthday $birthday): ?int => $birthday->turningAge(self::celebrationDate($birthday)->year))
                            ->placeholder('Year not given'),
                        TextEntry::make('notes')->placeholder('No notes')->columnSpanFull(),
                    ])
                    ->columns(2)
                    ->columnSpanFull(),
                Section::make('Member')
                    ->schema([
                        TextEntry::make('full_name')->label('Full name'),
                        TextEntry::make('preferred_name')->label('Goes by')->placeholder('–'),
                        TextEntry::make('tribe')->badge()->placeholder('–'),
                        TextEntry::make('email')->copyable(),
                        TextEntry::make('phone')->copyable()->placeholder('–'),
                        TextEntry::make('instagram_handle')
                            ->label('Instagram')
                            ->formatStateUsing(fn (string $state): string => '@'.$state)
                            ->url(fn (Birthday $birthday): string => 'https://www.instagram.com/'.$birthday->instagram_handle, shouldOpenInNewTab: true)
                            ->placeholder('–'),
                        TextEntry::make('created_at')->label('Shared')->dateTime('j M Y, g:i A', 'Africa/Lagos'),
                    ])
                    ->columns(3)
                    ->columnSpanFull(),
                Section::make('Photos')
                    ->schema([
                        ImageEntry::make('photo_paths')->hiddenLabel()->imageHeight(220)->placeholder('No photos'),
                        IconEntry::make('photo_consent')->label('Happy for the photos to be posted')->boolean(),
                    ])
                    ->columnSpanFull(),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->defaultSort(fn (Builder $query): Builder => $query->orderBy('birth_month')->orderBy('birth_day')->orderBy('full_name'))
            ->columns([
                TextColumn::make('birth_day')
                    ->label('Date')
                    ->state(fn (Birthday $birthday): string => self::celebrationDate($birthday)->format('D j M'))
                    ->weight('bold'),
                ImageColumn::make('photo_paths')->label('Photos')->circular()->stacked()->limit(3)->limitedRemainingText(),
                TextColumn::make('full_name')
                    ->label('Name')
                    ->formatStateUsing(fn (Birthday $birthday): string => $birthday->display_name)
                    ->description(fn (Birthday $birthday): ?string => $birthday->preferred_name ? $birthday->full_name : null)
                    ->searchable(['full_name', 'preferred_name', 'email', 'phone', 'instagram_handle']),
                TextColumn::make('turning')
                    ->state(fn (Birthday $birthday): ?string => ($age = $birthday->turningAge(self::celebrationDate($birthday)->year)) ? (string) $age : null)
                    ->placeholder('–'),
                TextColumn::make('tribe')->badge()->placeholder('–'),
                TextColumn::make('instagram_handle')->label('Instagram')->prefix('@')->placeholder('–')->toggleable(),
                TextColumn::make('email')->copyable()->toggleable(isToggledHiddenByDefault: true),
                TextColumn::make('phone')->copyable()->placeholder('–')->toggleable(isToggledHiddenByDefault: true),
                TextColumn::make('notes')->limit(60)->wrap()->placeholder('–'),
            ])
            ->filters([
                SelectFilter::make('birth_month')
                    ->label('Month')
                    ->options(collect(range(1, 12))->mapWithKeys(fn (int $month): array => [$month => Carbon::create(2000, $month)->format('F')])->all())
                    ->default(fn (): int => now()->startOfMonth()->addMonth()->month),
                SelectFilter::make('tribe')->options(collect(config('lri.tribes'))->mapWithKeys(fn (string $tribe): array => [$tribe => $tribe])->all()),
            ])
            ->emptyStateHeading('No birthdays this month')
            ->emptyStateDescription('Share the birthday form so more members can add theirs.')
            ->recordActions([
                ViewAction::make(),
            ]);
    }

    public static function canCreate(): bool
    {
        return false;
    }

    public static function getPages(): array
    {
        return [
            'index' => ManageBirthdays::route('/'),
        ];
    }

    /**
     * When the birthday is next celebrated.
     */
    private static function celebrationDate(Birthday $birthday): Carbon
    {
        return $birthday->celebrationDate(Birthday::upcomingYearFor($birthday->birth_month));
    }
}

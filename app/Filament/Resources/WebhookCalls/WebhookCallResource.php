<?php

namespace App\Filament\Resources\WebhookCalls;

use App\Actions\Checkout\HandleWebhookCall;
use App\Filament\Resources\WebhookCalls\Pages\ManageWebhookCalls;
use App\Models\WebhookCall;
use App\Support\AdminActivity;
use BackedEnum;
use Filament\Actions\Action;
use Filament\Actions\ViewAction;
use Filament\Infolists\Components\CodeEntry;
use Filament\Infolists\Components\TextEntry;
use Filament\Notifications\Notification;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\TernaryFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Throwable;
use UnitEnum;

/**
 * Webhooks the payment providers sent, to see what arrived and replay one that failed.
 */
class WebhookCallResource extends Resource
{
    protected static ?string $model = WebhookCall::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedSignal;

    protected static string|UnitEnum|null $navigationGroup = 'System';

    protected static ?string $navigationLabel = 'Webhooks';

    protected static ?int $navigationSort = 1;

    public static function getNavigationBadge(): ?string
    {
        $failed = WebhookCall::query()->whereNotNull('failed_at')->whereNull('processed_at')->count();

        return $failed > 0 ? (string) $failed : null;
    }

    public static function getNavigationBadgeColor(): string
    {
        return 'danger';
    }

    public static function infolist(Schema $schema): Schema
    {
        return $schema
            ->components([
                TextEntry::make('provider'),
                TextEntry::make('type'),
                TextEntry::make('event_id')->copyable(),
                TextEntry::make('event_created_at')->label('Sent')->dateTime('j M Y, g:i:s A', 'Africa/Lagos'),
                TextEntry::make('processed_at')->label('Handled')->dateTime('j M Y, g:i:s A', 'Africa/Lagos')->placeholder('Not yet'),
                TextEntry::make('error')->placeholder('–')->columnSpanFull(),
                CodeEntry::make('payload')
                    ->grammar('json')
                    ->jsonFlags(JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES)
                    ->columnSpanFull(),
            ])
            ->columns(2);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->defaultSort('created_at', 'desc')
            ->columns([
                TextColumn::make('created_at')->label('Received')->dateTime('j M, g:i:s A', 'Africa/Lagos')->sortable(),
                TextColumn::make('provider'),
                TextColumn::make('type')->searchable(),
                TextColumn::make('event_id')->searchable()->toggleable(isToggledHiddenByDefault: true),
                TextColumn::make('outcome')
                    ->badge()
                    ->state(fn (WebhookCall $call): string => match (true) {
                        $call->processed_at !== null && $call->error !== null => 'Noted',
                        $call->processed_at !== null => 'Handled',
                        $call->failed_at !== null => 'Failed',
                        default => 'Waiting',
                    })
                    ->color(fn (string $state): string => match ($state) {
                        'Handled' => 'success',
                        'Failed' => 'danger',
                        'Noted' => 'warning',
                        default => 'gray',
                    }),
                TextColumn::make('error')->limit(60)->placeholder('–')->wrap(),
            ])
            ->filters([
                TernaryFilter::make('failed')
                    ->label('Failed')
                    ->queries(
                        true: fn (Builder $query) => $query->whereNotNull('failed_at')->whereNull('processed_at'),
                        false: fn (Builder $query) => $query->whereNotNull('processed_at'),
                    ),
            ])
            ->recordActions([
                ViewAction::make(),
                Action::make('replay')
                    ->label('Handle again')
                    ->icon(Heroicon::OutlinedArrowPath)
                    ->requiresConfirmation()
                    ->modalDescription('Runs this webhook through again. Safe to repeat: a payment is never fulfilled twice.')
                    ->action(function (WebhookCall $record, HandleWebhookCall $handleWebhookCall): void {
                        AdminActivity::record("Ran the {$record->provider} webhook \"{$record->type}\" again", $record);

                        try {
                            $handleWebhookCall->handle($record);
                        } catch (Throwable $exception) {
                            Notification::make()->title('It failed again')->body($exception->getMessage())->danger()->send();

                            return;
                        }

                        Notification::make()->title('Handled')->body($record->error)->success()->send();
                    }),
            ]);
    }

    public static function canCreate(): bool
    {
        return false;
    }

    public static function getPages(): array
    {
        return [
            'index' => ManageWebhookCalls::route('/'),
        ];
    }
}

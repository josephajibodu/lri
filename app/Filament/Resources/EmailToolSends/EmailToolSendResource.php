<?php

namespace App\Filament\Resources\EmailToolSends;

use App\Enums\EmailToolEvent;
use App\Enums\EmailToolSendStatus;
use App\Filament\Resources\EmailToolSends\Pages\ManageEmailToolSends;
use App\Jobs\SendToEmailTool;
use App\Models\EmailToolSend;
use App\Support\AdminActivity;
use BackedEnum;
use Filament\Actions\Action;
use Filament\Notifications\Notification;
use Filament\Resources\Resource;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use UnitEnum;

/**
 * What the email tool has been told about buyers, to see what went out and send again anything that failed.
 */
class EmailToolSendResource extends Resource
{
    protected static ?string $model = EmailToolSend::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedEnvelope;

    protected static string|UnitEnum|null $navigationGroup = 'System';

    protected static ?string $navigationLabel = 'Email tool';

    protected static ?string $modelLabel = 'email tool send';

    protected static ?int $navigationSort = 2;

    public static function getNavigationBadge(): ?string
    {
        $failed = EmailToolSend::query()->where('status', EmailToolSendStatus::Failed)->count();

        return $failed > 0 ? (string) $failed : null;
    }

    public static function getNavigationBadgeColor(): string
    {
        return 'danger';
    }

    public static function table(Table $table): Table
    {
        return $table
            ->defaultSort('created_at', 'desc')
            ->columns([
                TextColumn::make('created_at')->label('Queued')->dateTime('j M, g:i:s A', 'Africa/Lagos')->sortable(),
                TextColumn::make('order.reference')->label('Order')->searchable(),
                TextColumn::make('order.buyer_email')->label('Buyer')->searchable(),
                TextColumn::make('event')->badge()->color('gray'),
                TextColumn::make('tool')->toggleable(isToggledHiddenByDefault: true),
                TextColumn::make('status')->badge(),
                TextColumn::make('attempts')->alignEnd()->toggleable(isToggledHiddenByDefault: true),
                TextColumn::make('sent_at')->label('Sent')->dateTime('j M, g:i:s A', 'Africa/Lagos')->placeholder('–'),
                TextColumn::make('error')->limit(60)->placeholder('–')->wrap(),
            ])
            ->filters([
                SelectFilter::make('status')->options(EmailToolSendStatus::class),
                SelectFilter::make('event')->options(EmailToolEvent::class),
            ])
            ->recordActions([
                Action::make('sendAgain')
                    ->label('Send again')
                    ->icon(Heroicon::OutlinedArrowPath)
                    ->requiresConfirmation()
                    ->modalDescription('Tells the email tool this news again, in the background. Safe to repeat.')
                    ->visible(fn (EmailToolSend $record): bool => $record->status === EmailToolSendStatus::Failed)
                    ->action(function (EmailToolSend $record): void {
                        $record->update(['status' => EmailToolSendStatus::Waiting, 'failed_at' => null, 'error' => null]);

                        SendToEmailTool::dispatch($record);
                        AdminActivity::record("Sent the news about order {$record->order->reference} to the email tool again", $record);

                        Notification::make()->title('Queued to send again')->success()->send();
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
            'index' => ManageEmailToolSends::route('/'),
        ];
    }
}

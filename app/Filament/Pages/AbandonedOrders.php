<?php

namespace App\Filament\Pages;

use App\Actions\Checkout\SendOrderReminder;
use App\Enums\AdminPermission;
use App\Enums\OrderStatus;
use App\Filament\Resources\Orders\OrderResource;
use App\Models\Order;
use BackedEnum;
use Filament\Actions\Action;
use Filament\Actions\BulkAction;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Filament\Schemas\Components\EmbeddedTable;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Concerns\InteractsWithTable;
use Filament\Tables\Contracts\HasTable;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;
use UnitEnum;

/**
 * Buyers who left without paying, and the one reminder email organisers can send them to come back and finish. Each
 * buyer gets it at most once, and never after they've bought or ordered again (see Order::scopeAwaitingReminder).
 */
class AbandonedOrders extends Page implements HasTable
{
    use InteractsWithTable;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedEnvelope;

    protected static ?string $title = 'Abandoned orders';

    protected static string|UnitEnum|null $navigationGroup = 'Marketing';

    protected static ?int $navigationSort = 0;

    /**
     * The orders ready for a reminder, worked out once per request.
     *
     * @var Collection<int, int>|null
     */
    private ?Collection $readyIds = null;

    public static function canAccess(): bool
    {
        return auth()->user()?->can(AdminPermission::SendReminders->value) ?? false;
    }

    public function getSubheading(): string
    {
        $sent = Order::query()->where('status', OrderStatus::Abandoned)->whereNotNull('reminder_sent_at')->count();
        $summary = number_format($this->readyIds()->count()).' ready for a reminder · '.number_format($sent).' sent';

        return SendOrderReminder::ticketsOnSale()
            ? $summary
            : $summary.' · Reminders are paused while no tickets are on sale, because the email’s button would lead nowhere.';
    }

    public function content(Schema $schema): Schema
    {
        return $schema->components([
            EmbeddedTable::make(),
        ]);
    }

    /**
     * @return array<Action>
     */
    protected function getHeaderActions(): array
    {
        return [
            Action::make('previewEmail')
                ->label('See the email')
                ->icon(Heroicon::OutlinedEye)
                ->color('gray')
                ->visible(fn (): bool => ! app()->isProduction())
                ->url(fn (): string => route('mail-previews.index', 'abandoned-order'), shouldOpenInNewTab: true),
            Action::make('remindEveryone')
                ->label(fn (): string => 'Email everyone ready ('.$this->readyIds()->count().')')
                ->icon(Heroicon::OutlinedPaperAirplane)
                ->disabled(fn (): bool => $this->readyIds()->isEmpty() || ! SendOrderReminder::ticketsOnSale())
                ->requiresConfirmation()
                ->modalHeading('Email everyone ready for a reminder?')
                ->modalDescription(fn (): string => $this->readyIds()->count() === 1
                    ? 'One buyer gets the reminder to come back and finish. They only ever get it once.'
                    : $this->readyIds()->count().' buyers each get the reminder to come back and finish. Each one only ever gets it once.')
                ->modalSubmitActionLabel('Send')
                ->action(fn (SendOrderReminder $sendOrderReminder) => $this->remind(Order::query()->whereKey($this->readyIds())->get(), $sendOrderReminder)),
        ];
    }

    public function table(Table $table): Table
    {
        return $table
            ->query(Order::query()->where('status', OrderStatus::Abandoned)->with(['ticketType', 'coupon']))
            ->defaultSort('abandoned_at', 'desc')
            ->searchPlaceholder('Name, email or order number')
            ->emptyStateHeading('No abandoned orders')
            ->emptyStateDescription('Orders show up here once a buyer leaves without paying and their hold runs out.')
            ->recordUrl(fn (Order $record): ?string => OrderResource::canView($record) ? OrderResource::getUrl('view', ['record' => $record]) : null)
            ->columns([
                TextColumn::make('buyer_name')
                    ->label('Buyer')
                    ->description(fn (Order $record): string => $record->buyer_email)
                    ->searchable(['buyer_name', 'buyer_email', 'reference']),
                TextColumn::make('quantity')
                    ->label('Tickets')
                    ->numeric(),
                TextColumn::make('amount')
                    ->label('Total')
                    ->formatStateUsing(fn (Order $record): string => $record->total()->format())
                    ->description(fn (Order $record): ?string => $record->coupon ? "Coupon {$record->coupon->code}" : null),
                IconColumn::make('marketing_consent')
                    ->label('Event emails')
                    ->tooltip(fn (Order $record): string => $record->marketing_consent ? 'Ticked the box for event emails' : 'Didn’t tick the box for event emails. The reminder is about their own order, so it still goes.')
                    ->boolean(),
                TextColumn::make('abandoned_at')
                    ->label('Left')
                    ->since()
                    ->dateTimeTooltip('j M Y, g:i A', 'Africa/Lagos')
                    ->sortable(),
                TextColumn::make('reminder')
                    ->label('Reminder')
                    ->badge()
                    ->state(fn (Order $record): string => $this->reminderState($record))
                    ->color(fn (string $state): string => match (true) {
                        $state === 'Ready' => 'warning',
                        Str::startsWith($state, 'Sent') => 'success',
                        default => 'gray',
                    })
                    ->tooltip(fn (string $state): ?string => $state === 'Skipped'
                        ? 'This buyer has bought, ordered again or already had a reminder, so they won’t get one for this order.'
                        : null),
            ])
            ->filters([
                SelectFilter::make('reminder')
                    ->label('Reminder')
                    ->options(['ready' => 'Ready', 'sent' => 'Sent', 'skipped' => 'Skipped'])
                    ->query(fn (Builder $query, array $data): Builder => match ($data['value'] ?? null) {
                        'ready' => $query->whereKey($this->readyIds()),
                        'sent' => $query->whereNotNull('reminder_sent_at'),
                        'skipped' => $query->whereNull('reminder_sent_at')->whereKeyNot($this->readyIds()),
                        default => $query,
                    }),
            ])
            ->recordActions([
                Action::make('remind')
                    ->label('Send reminder')
                    ->icon(Heroicon::OutlinedPaperAirplane)
                    ->visible(fn (Order $record): bool => $this->readyIds()->contains($record->id) && SendOrderReminder::ticketsOnSale())
                    ->requiresConfirmation()
                    ->modalHeading('Send the reminder?')
                    ->modalDescription(fn (Order $record): string => "{$record->buyer_email} gets the email to come back and finish. They only ever get it once.")
                    ->modalSubmitActionLabel('Send')
                    ->action(fn (Order $record, SendOrderReminder $sendOrderReminder) => $this->remind(collect([$record]), $sendOrderReminder)),
            ])
            ->toolbarActions([
                BulkAction::make('remindSelected')
                    ->label('Send reminder')
                    ->icon(Heroicon::OutlinedPaperAirplane)
                    ->disabled(fn (): bool => ! SendOrderReminder::ticketsOnSale())
                    ->requiresConfirmation()
                    ->modalHeading('Send the reminder to the chosen buyers?')
                    ->modalDescription('Buyers who have bought since, ordered again or already had it are skipped.')
                    ->modalSubmitActionLabel('Send')
                    ->action(fn (Collection $records, SendOrderReminder $sendOrderReminder) => $this->remind($records, $sendOrderReminder)),
            ]);
    }

    /**
     * Send the reminder for each order that still needs one, and say how many went.
     *
     * @param  Collection<int, Order>  $orders
     */
    private function remind(Collection $orders, SendOrderReminder $sendOrderReminder): void
    {
        if (! SendOrderReminder::ticketsOnSale()) {
            Notification::make()->title('No tickets are on sale, so no reminders went')->warning()->send();

            return;
        }

        $sent = $orders->filter(fn (Order $order): bool => $sendOrderReminder->handle($order))->count();
        $skipped = $orders->count() - $sent;
        $this->readyIds = null;

        Notification::make()
            ->title($sent === 0 ? 'No reminders went' : ($sent === 1 ? 'Reminder on its way' : "{$sent} reminders on their way"))
            ->body($skipped > 0 ? Str::plural('buyer', $skipped, prependCount: true).' skipped: they had bought, ordered again or already had one.' : null)
            ->status($sent === 0 ? 'warning' : 'success')
            ->send();
    }

    /**
     * Where an order stands with its reminder, as the table shows it.
     */
    private function reminderState(Order $order): string
    {
        return match (true) {
            $order->reminder_sent_at !== null => 'Sent '.$order->reminder_sent_at->timezone('Africa/Lagos')->format('j M'),
            $this->readyIds()->contains($order->id) => 'Ready',
            default => 'Skipped',
        };
    }

    /**
     * @return Collection<int, int>
     */
    private function readyIds(): Collection
    {
        return $this->readyIds ??= Order::query()->awaitingReminder()->pluck('id');
    }
}

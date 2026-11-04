<?php

namespace App\Filament\Desk\Pages;

use App\Actions\Tickets\HandOverHeadset;
use App\Actions\Tickets\NameTicket;
use App\Enums\OrderStatus;
use App\Exceptions\TicketNotUsable;
use App\Models\Ticket;
use BackedEnum;
use Filament\Actions\Action;
use Filament\Forms\Components\TextInput;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Filament\Panel;
use Filament\Schemas\Components\EmbeddedTable;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Concerns\InteractsWithTable;
use Filament\Tables\Contracts\HasTable;
use Filament\Tables\Grouping\Group;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

/**
 * Where staff hand out headsets on the day. They find a ticket by its code, the holder's name or email, or the
 * buyer's name, email or order number; fill in the name and email of anyone the buyer didn't name; and hand over
 * one headset per paid ticket.
 */
class HeadsetDesk extends Page implements HasTable
{
    use InteractsWithTable;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedTicket;

    protected static ?string $title = 'Headset desk';

    public static function getRoutePath(Panel $panel): string
    {
        return '/';
    }

    public function getSubheading(): string
    {
        $paid = Ticket::query()->whereRelation('order', 'status', OrderStatus::Paid);
        $handedOut = (clone $paid)->whereNotNull('checked_in_at')->count();

        return number_format($handedOut).' of '.number_format($paid->count()).' headsets handed out';
    }

    public function content(Schema $schema): Schema
    {
        return $schema->components([
            EmbeddedTable::make(),
        ]);
    }

    public function table(Table $table): Table
    {
        return $table
            ->query(Ticket::query()->with(['order', 'checkedInBy']))
            ->defaultSort('id')
            ->defaultGroup(
                Group::make('order.reference')
                    ->label('Order')
                    ->getTitleFromRecordUsing(fn (Ticket $record): string => $record->order->reference.' · bought by '.$record->order->buyer_name)
                    ->titlePrefixedWithLabel(false),
            )
            ->groupingSettingsHidden()
            ->searchPlaceholder('Code, name, email or order number')
            ->persistSearchInSession()
            ->columns([
                TextColumn::make('code')
                    ->label('Ticket')
                    ->fontFamily('mono')
                    ->weight('bold')
                    ->searchable(query: fn (Builder $query, string $search): Builder => $query->orWhereRaw(
                        "replace(code, '-', '') like ?",
                        ['%'.strtoupper(str_replace(['-', ' '], '', $search)).'%'],
                    )),
                TextColumn::make('holder_name')
                    ->label('Holder')
                    ->placeholder('No name yet')
                    ->description(fn (Ticket $record): ?string => $record->holder_email)
                    ->searchable(['holder_name', 'holder_email']),
                TextColumn::make('order.buyer_name')
                    ->label('Bought by')
                    ->description(fn (Ticket $record): string => $record->order->buyer_email)
                    ->searchable(['buyer_name', 'buyer_email', 'reference'])
                    ->visibleFrom('md'),
                TextColumn::make('desk_status')
                    ->label('Headset')
                    ->badge()
                    ->state(fn (Ticket $record): string => match (true) {
                        ! $record->isValid() => 'Not paid',
                        $record->isCollected() => 'Collected',
                        ! $record->isNamed() => 'Needs a name',
                        default => 'Ready',
                    })
                    ->color(fn (string $state): string => match ($state) {
                        'Not paid' => 'danger',
                        'Collected' => 'gray',
                        'Needs a name' => 'warning',
                        default => 'success',
                    })
                    ->description(fn (Ticket $record): ?string => $record->isCollected()
                        ? $record->checked_in_at->timezone('Africa/Lagos')->format('j M, g:i A').($record->checkedInBy ? ' · '.$record->checkedInBy->name : '')
                        : null),
            ])
            ->recordActions([
                Action::make('fillInName')
                    ->label(fn (Ticket $record): string => $record->isNamed() ? 'Change name' : 'Fill in name')
                    ->icon(Heroicon::OutlinedPencilSquare)
                    ->color('gray')
                    ->visible(fn (Ticket $record): bool => $record->isValid() && ! $record->isCollected())
                    ->modalHeading(fn (Ticket $record): string => 'Ticket '.$record->code)
                    ->modalDescription('We’ll email this person their ticket.')
                    ->fillForm(fn (Ticket $record): array => [
                        'name' => $record->holder_name,
                        'email' => $record->holder_email,
                    ])
                    ->schema([
                        TextInput::make('name')->label('Their name')->required()->maxLength(120),
                        TextInput::make('email')->label('Their email')->email()->required()->maxLength(190),
                    ])
                    ->modalSubmitActionLabel('Save')
                    ->action(function (Ticket $record, array $data, Action $action, NameTicket $nameTicket): void {
                        try {
                            $nameTicket->handle($record, $data['name'], $data['email']);
                        } catch (TicketNotUsable $exception) {
                            Notification::make()->title($exception->getMessage())->danger()->send();
                            $action->halt();
                        }

                        Notification::make()->title('Saved. Now hand over the headset.')->success()->send();
                    }),
                Action::make('handOver')
                    ->label('Hand over headset')
                    ->icon(Heroicon::OutlinedCheckCircle)
                    ->color('success')
                    ->button()
                    ->visible(fn (Ticket $record): bool => $record->isValid() && $record->isNamed() && ! $record->isCollected())
                    ->requiresConfirmation()
                    ->modalHeading(fn (Ticket $record): string => 'Hand a headset to '.$record->holder_name.'?')
                    ->modalDescription(fn (Ticket $record): string => 'Ticket '.$record->code.'. This ticket can’t be used for another headset after this.')
                    ->modalSubmitActionLabel('Handed over')
                    ->action(function (Ticket $record, HandOverHeadset $handOverHeadset): void {
                        try {
                            $handOverHeadset->handle($record, auth()->user());
                        } catch (TicketNotUsable $exception) {
                            Notification::make()->title($exception->getMessage())->danger()->send();

                            return;
                        }

                        Notification::make()->title('Headset handed to '.$record->holder_name.'.')->success()->send();
                    }),
            ])
            ->emptyStateHeading('No tickets found')
            ->emptyStateDescription('Try the code on their ticket, their name or email, or the name of whoever bought it.');
    }
}

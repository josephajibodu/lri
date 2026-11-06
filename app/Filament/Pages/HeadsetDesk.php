<?php

namespace App\Filament\Pages;

use App\Actions\Tickets\HandOverHeadset;
use App\Actions\Tickets\NameTicket;
use App\Enums\AdminPermission;
use App\Enums\OrderStatus;
use App\Exceptions\TicketNotUsable;
use App\Models\Ticket;
use App\Support\PersonName;
use BackedEnum;
use Closure;
use Filament\Actions\Action;
use Filament\Forms\Components\TextInput;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Filament\Schemas\Components\EmbeddedTable;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Concerns\InteractsWithTable;
use Filament\Tables\Contracts\HasTable;
use Filament\Tables\Grouping\Group;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Relations\Relation;
use Livewire\Attributes\Url;
use UnitEnum;

/**
 * Where staff hand out headsets on the day. They find a ticket by its code, the holder's name or email, or the
 * buyer's name, email or order number; fill in the name and email of anyone the buyer didn't name; and hand over
 * one headset per paid ticket. A ticket with no name on it has nothing to check someone against, so the person has to
 * show its code, which stays hidden here until it's searched for in full. Otherwise anyone could say a buyer got them
 * a ticket and be given a spare one.
 */
class HeadsetDesk extends Page implements HasTable
{
    use InteractsWithTable;

    /**
     * The search, kept in the address. A ticket's QR code links here with its code as the search, so scanning it
     * with a phone camera opens the desk on that ticket.
     */
    #[Url(as: 'search')]
    public $tableSearch = '';

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedTicket;

    protected static ?string $title = 'Headset desk';

    protected static string|UnitEnum|null $navigationGroup = 'Tickets';

    protected static ?int $navigationSort = 0;

    /**
     * Only super admins, until the desk is opened with HEADSET_DESK_OPEN. Then, anyone whose roles include it.
     */
    public static function canAccess(): bool
    {
        $user = auth()->user();

        if (! config('lri.headset_desk.open')) {
            return $user?->isSuperAdmin() ?? false;
        }

        return $user?->can(AdminPermission::UseHeadsetDesk->value) ?? false;
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
            ->query(Ticket::query()->with([
                'checkedInBy',
                'order' => fn (Relation $query) => $query->withCount(['tickets as unnamed_tickets_count' => fn (Builder $query): Builder => $query
                    ->where(fn (Builder $query): Builder => $query->whereNull('holder_name')->orWhereNull('holder_email'))]),
            ]))
            ->defaultSort('id')
            ->defaultGroup(
                Group::make('order.reference')
                    ->label('Order')
                    ->getTitleFromRecordUsing(fn (Ticket $record): string => $record->order->reference.' · bought by '.$record->order->buyer_name)
                    ->titlePrefixedWithLabel(false),
            )
            ->groupingSettingsHidden()
            ->searchPlaceholder('Code, name, email or order number')
            ->columns([
                TextColumn::make('code')
                    ->label('Ticket')
                    ->fontFamily('mono')
                    ->weight('bold')
                    ->formatStateUsing(fn (string $state, Ticket $record): string => $record->isNamed() || $this->isCodeOf($record, $this->tableSearch) ? $state : '••••-••••')
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
                        $record->order->status === OrderStatus::Disputed => 'Payment disputed',
                        $record->order->status === OrderStatus::TestPayment => 'Test payment',
                        ! $record->isValid() => 'Not paid',
                        $record->isCollected() => 'Headset already given',
                        ! $record->isNamed() => 'Needs a name',
                        default => 'Paid, ready',
                    })
                    ->color(fn (string $state): string => match ($state) {
                        'Not paid', 'Payment disputed', 'Test payment', 'Headset already given' => 'danger',
                        'Needs a name' => 'warning',
                        default => 'success',
                    })
                    ->description(fn (Ticket $record): ?string => match (true) {
                        $record->isCollected() => 'Given '.$record->checked_in_at->timezone('Africa/Lagos')->format('j M, g:i A').($record->checkedInBy ? ' by '.$record->checkedInBy->name : ''),
                        $record->isValid() && ! $record->isNamed() => 'Order has '.$record->order->unnamed_tickets_count.' unnamed '.str('ticket')->plural($record->order->unnamed_tickets_count),
                        default => null,
                    }),
            ])
            ->recordActions([
                Action::make('giveHeadset')
                    ->label('Give headset')
                    ->icon(Heroicon::OutlinedCheckCircle)
                    ->color('success')
                    ->button()
                    ->visible(fn (Ticket $record): bool => $record->isValid() && $record->isNamed() && ! $record->isCollected())
                    ->authorize(fn (): bool => static::canAccess())
                    ->requiresConfirmation()
                    ->modalHeading(fn (Ticket $record): string => 'Give a headset to '.$record->holder_name.'?')
                    ->modalDescription(fn (Ticket $record): string => 'Ticket '.$record->code.'. This ticket can’t be used for another headset after this.')
                    ->modalSubmitActionLabel('Headset given')
                    ->action(fn (Ticket $record, HandOverHeadset $handOverHeadset) => $this->giveHeadset($record, $handOverHeadset)),
                Action::make('saveAndGiveHeadset')
                    ->label('Save and give headset')
                    ->icon(Heroicon::OutlinedCheckCircle)
                    ->color('warning')
                    ->button()
                    ->visible(fn (Ticket $record): bool => $record->isValid() && ! $record->isNamed() && ! $record->isCollected())
                    ->authorize(fn (): bool => static::canAccess())
                    ->modalHeading('Who’s collecting this ticket?')
                    ->modalDescription('Ask for the ticket’s code, from their QR code or the buyer’s email or receipt. Then type in their name and email, and we’ll email them their ticket.')
                    ->fillForm(fn (Ticket $record): array => ['code' => $this->isCodeOf($record, $this->tableSearch) ? $record->code : null])
                    ->schema([
                        TextInput::make('code')
                            ->label('Ticket code')
                            ->required()
                            ->helperText('No code, no headset. The buyer can read it out from their email or receipt.')
                            ->rule(fn (Ticket $record): Closure => function (string $attribute, mixed $value, Closure $fail) use ($record): void {
                                if (! $this->isCodeOf($record, (string) $value)) {
                                    $fail('That isn’t this ticket’s code.');
                                }
                            }),
                        ...static::holderFields(),
                    ])
                    ->modalSubmitActionLabel('Save and give headset')
                    ->action(function (Ticket $record, array $data, Action $action, NameTicket $nameTicket, HandOverHeadset $handOverHeadset): void {
                        try {
                            $record = $nameTicket->handle($record, $data['name'], $data['email']);
                        } catch (TicketNotUsable $exception) {
                            Notification::make()->title($exception->getMessage())->danger()->send();
                            $action->halt();
                        }

                        $this->giveHeadset($record, $handOverHeadset);
                    }),
                Action::make('changeName')
                    ->label('Change name')
                    ->icon(Heroicon::OutlinedPencilSquare)
                    ->color('gray')
                    ->visible(fn (Ticket $record): bool => $record->isValid() && $record->isNamed() && ! $record->isCollected())
                    ->authorize(fn (): bool => static::canAccess())
                    ->modalHeading(fn (Ticket $record): string => 'Ticket '.$record->code)
                    ->modalDescription('Fix the name or email. We’ll email them their ticket again.')
                    ->fillForm(fn (Ticket $record): array => [
                        'name' => $record->holder_name,
                        'email' => $record->holder_email,
                    ])
                    ->schema(static::holderFields())
                    ->modalSubmitActionLabel('Save')
                    ->action(function (Ticket $record, array $data, Action $action, NameTicket $nameTicket): void {
                        try {
                            $nameTicket->handle($record, $data['name'], $data['email']);
                        } catch (TicketNotUsable $exception) {
                            Notification::make()->title($exception->getMessage())->danger()->send();
                            $action->halt();
                        }

                        Notification::make()->title('Saved.')->success()->send();
                    }),
            ])
            ->emptyStateHeading('No tickets found')
            ->emptyStateDescription('Try the code on their ticket, their name or email, or the name of whoever bought it.');
    }

    /**
     * The name and email of the person a ticket is for.
     *
     * @return array<TextInput>
     */
    protected static function holderFields(): array
    {
        return [
            TextInput::make('name')
                ->label('Their name')
                ->required()
                ->maxLength(120)
                ->notRegex(PersonName::NotAllowed)
                ->validationMessages(['not_regex' => PersonName::NotAllowedMessage]),
            TextInput::make('email')->label('Their email')->email()->required()->maxLength(190),
        ];
    }

    /**
     * Whether this is the ticket's code, with or without its dash, in any case.
     */
    protected function isCodeOf(Ticket $ticket, ?string $typed): bool
    {
        return str_replace('-', '', $ticket->code) === strtoupper(str_replace(['-', ' '], '', (string) $typed));
    }

    /**
     * Record the headset as given, and say how it went.
     */
    protected function giveHeadset(Ticket $ticket, HandOverHeadset $handOverHeadset): void
    {
        try {
            $handOverHeadset->handle($ticket, auth()->user());
        } catch (TicketNotUsable $exception) {
            Notification::make()->title($exception->getMessage())->danger()->send();

            return;
        }

        Notification::make()->title('Headset given to '.$ticket->holder_name.'.')->success()->send();
    }
}

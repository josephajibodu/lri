<?php

namespace App\Filament\Resources\Coupons;

use App\Enums\CouponDiscountType;
use App\Enums\CouponStatus;
use App\Enums\OrderStatus;
use App\Filament\Resources\Coupons\Pages\ManageCoupons;
use App\Filament\Resources\Orders\OrderResource;
use App\Models\Coupon;
use App\Models\TicketType;
use App\Support\Money;
use App\Support\ReadableCode;
use BackedEnum;
use Carbon\Carbon;
use Carbon\CarbonInterface;
use Closure;
use Filament\Actions\Action;
use Filament\Actions\ViewAction;
use Filament\Forms\Components\DateTimePicker;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Forms\Components\ToggleButtons;
use Filament\Infolists\Components\TextEntry;
use Filament\Notifications\Notification;
use Filament\Resources\Resource;
use Filament\Schemas\Components\Callout;
use Filament\Schemas\Components\Grid;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Text;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Components\Utilities\Set;
use Filament\Schemas\Schema;
use Filament\Support\Enums\FontWeight;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use UnitEnum;

/**
 * Coupons buyers can type at checkout. They can be created and viewed, then only disabled or turned back on: there is
 * deliberately no way to edit one, so every order that used a coupon was priced by the terms still on record.
 */
class CouponResource extends Resource
{
    protected static ?string $model = Coupon::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedReceiptPercent;

    protected static string|UnitEnum|null $navigationGroup = 'Tickets';

    protected static ?int $navigationSort = 3;

    protected static ?string $recordTitleAttribute = 'code';

    public static function form(Schema $schema): Schema
    {
        return $schema
            ->columns(1)
            ->components([
                TextInput::make('code')
                    ->label('Coupon code')
                    ->default(fn (): string => ReadableCode::generate(8))
                    ->required()
                    ->maxLength(40)
                    ->regex('/^[A-Za-z0-9-]+$/')
                    ->validationMessages(['regex' => 'Use only letters, numbers and dashes, so it’s easy to type.'])
                    ->rule(fn (): Closure => function (string $attribute, mixed $value, Closure $fail): void {
                        if (Coupon::query()->withCode((string) $value)->exists()) {
                            $fail('Another coupon already uses this code.');
                        }
                    })
                    ->extraInputAttributes(['style' => 'font-family: ui-monospace, SFMono-Regular, Menlo, monospace; letter-spacing: .04em; text-transform: uppercase;'])
                    ->suffixAction(
                        Action::make('newCode')
                            ->icon(Heroicon::OutlinedArrowPath)
                            ->tooltip('Make a new code')
                            ->action(fn (Set $set) => $set('code', ReadableCode::generate(8))),
                    )
                    ->helperText('What buyers type. Keep this one or use your own, like LRI20.'),
                TextInput::make('note')
                    ->label('What it’s for')
                    ->hint('Only organisers see this')
                    ->maxLength(255)
                    ->placeholder('Live test purchase'),
                Section::make('Discount')
                    ->contained(false)
                    ->schema([
                        Grid::make(['default' => 1, 'sm' => 5])
                            ->schema([
                                ToggleButtons::make('discount_type')
                                    ->columnSpan(['default' => 1, 'sm' => 3])
                                    ->label('Type of discount')
                                    ->hiddenLabel()
                                    ->options(CouponDiscountType::class)
                                    ->default(CouponDiscountType::Percentage)
                                    ->grouped()
                                    ->required()
                                    ->live()
                                    ->afterStateUpdated(fn (Set $set) => $set('discount_value', null)),
                                TextInput::make('discount_value')
                                    ->columnSpan(['default' => 1, 'sm' => 2])
                                    ->label('Discount')
                                    ->hiddenLabel()
                                    ->prefix(fn (Get $get): ?string => static::isPercentage($get) ? null : '₦')
                                    ->suffix(fn (Get $get): ?string => static::isPercentage($get) ? '%' : null)
                                    ->placeholder(fn (Get $get): string => static::isPercentage($get) ? '20' : '2000')
                                    ->numeric()
                                    ->minValue(1)
                                    ->maxValue(fn (Get $get): ?int => static::isPercentage($get) ? 99 : null)
                                    ->rule('integer', fn (Get $get): bool => static::isPercentage($get))
                                    ->required()
                                    ->validationMessages([
                                        'integer' => 'Use a whole number, like 20.',
                                        'max' => 'The most a coupon can take off is 99%, because buyers always pay at least ₦100.',
                                    ])
                                    ->live(debounce: 400)
                                    ->dehydrateStateUsing(fn (string $state, Get $get): int => static::storedDiscount($state, $get)),
                            ]),
                        Callout::make(fn (Get $get): string => static::previewHeading($get))
                            ->description(fn (Get $get): ?string => static::previewDetail($get))
                            ->icon(Heroicon::OutlinedTicket)
                            ->iconColor('primary')
                            ->visible(fn (): bool => static::ticketToPreview() !== null),
                    ]),
                Section::make('Limits')
                    ->contained(false)
                    ->schema([
                        Text::make('Leave these off and it works on any order, any time, until you disable it.'),
                        Toggle::make('limit_dates')
                            ->label('Set start and end dates')
                            ->live()
                            ->dehydrated(false),
                        Grid::make(['default' => 1, 'sm' => 2])
                            ->extraAttributes(static::revealedFieldAttributes())
                            ->visible(fn (Get $get): bool => (bool) $get('limit_dates'))
                            ->schema([
                                DateTimePicker::make('starts_at')
                                    ->label('Starts')
                                    ->native(false)
                                    ->seconds(false)
                                    ->displayFormat('j M Y, g:i A')
                                    ->timezone('Africa/Lagos')
                                    ->placeholder('Straight away'),
                                DateTimePicker::make('ends_at')
                                    ->label('Ends')
                                    ->native(false)
                                    ->seconds(false)
                                    ->displayFormat('j M Y, g:i A')
                                    ->timezone('Africa/Lagos')
                                    ->placeholder('Until you disable it')
                                    ->live()
                                    ->afterStateUpdated(function (?string $state, ?string $old, Set $set): void {
                                        // Choosing just a day means all of that day, not the midnight it starts with.
                                        $chosen = filled($state) ? Carbon::parse($state)->timezone('Africa/Lagos') : null;

                                        if (blank($old) && $chosen?->format('H:i') === '00:00') {
                                            $set('ends_at', $chosen->setTime(23, 59)->timezone(config('app.timezone'))->toDateTimeString());
                                        }
                                    })
                                    ->minDate(fn (): CarbonInterface => now('Africa/Lagos'))
                                    ->requiredWithout('starts_at')
                                    ->after('starts_at')
                                    ->validationMessages([
                                        'required_without' => 'Choose a start or an end date, or switch this off.',
                                        'after' => 'The end has to be after the start.',
                                        'after_or_equal' => 'The end has to be in the future.',
                                    ]),
                            ]),
                        Toggle::make('limit_orders')
                            ->label('Limit how many orders can use it')
                            ->live()
                            ->dehydrated(false),
                        Grid::make(['default' => 1, 'sm' => 2])
                            ->extraAttributes(static::revealedFieldAttributes())
                            ->visible(fn (Get $get): bool => (bool) $get('limit_orders'))
                            ->schema([
                                TextInput::make('max_orders')
                                    ->label('Number of orders')
                                    ->hiddenLabel()
                                    ->suffix('orders')
                                    ->integer()
                                    ->minValue(1)
                                    ->required()
                                    ->helperText('Use 1 for a one-time coupon.'),
                            ]),
                        Toggle::make('limit_tickets')
                            ->label('Limit how many tickets it covers in an order')
                            ->visible(fn (Get $get): bool => static::isPercentage($get))
                            ->live()
                            ->dehydrated(false),
                        Grid::make(['default' => 1, 'sm' => 2])
                            ->extraAttributes(static::revealedFieldAttributes())
                            ->visible(fn (Get $get): bool => static::isPercentage($get) && (bool) $get('limit_tickets'))
                            ->schema([
                                TextInput::make('max_tickets_per_order')
                                    ->label('Number of tickets')
                                    ->hiddenLabel()
                                    ->suffix('tickets')
                                    ->integer()
                                    ->minValue(1)
                                    ->required()
                                    ->helperText('Extra tickets pay full price.'),
                            ]),
                        Toggle::make('limit_ticket_types')
                            ->label('Only for certain tickets')
                            ->live()
                            ->dehydrated(false),
                        Select::make('ticketTypes')
                            ->label('Tickets')
                            ->hiddenLabel()
                            ->extraFieldWrapperAttributes(static::revealedFieldAttributes())
                            ->visible(fn (Get $get): bool => (bool) $get('limit_ticket_types'))
                            ->relationship('ticketTypes', 'name')
                            ->multiple()
                            ->preload()
                            ->required(),
                    ]),
            ]);
    }

    public static function infolist(Schema $schema): Schema
    {
        return $schema
            ->components([
                TextEntry::make('code')->copyable()->fontFamily('mono')->weight(FontWeight::Bold),
                TextEntry::make('status')->badge()->state(fn (Coupon $record): CouponStatus => $record->status()),
                TextEntry::make('discount')->state(fn (Coupon $record): string => $record->describe()),
                TextEntry::make('note')->label('What it’s for')->placeholder('–'),
                TextEntry::make('window')->label('When it works')->state(fn (Coupon $record): string => static::window($record)),
                TextEntry::make('max_orders')->label('How many orders can use it')->placeholder('No limit'),
                TextEntry::make('ticketTypes.name')->label('Which tickets it works on')->placeholder('Every ticket'),
                TextEntry::make('created_at')->label('Created')->dateTime('j M Y, g:i A', 'Africa/Lagos'),
                TextEntry::make('disabled_at')->label('Disabled')->dateTime('j M Y, g:i A', 'Africa/Lagos')->placeholder('–'),
            ])
            ->columns(2);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->modifyQueryUsing(fn (Builder $query): Builder => $query
                ->withCount([
                    'orders as orders_taken_count' => fn (Builder $query) => $query->holdingTickets(),
                    'orders as orders_paid_count' => fn (Builder $query) => $query->where('status', OrderStatus::Paid),
                ])
                ->withSum(['orders as discount_given' => fn (Builder $query) => $query->where('status', OrderStatus::Paid)], 'discount_amount'))
            ->defaultSort('created_at', 'desc')
            ->columns([
                TextColumn::make('code')
                    ->searchable()
                    ->copyable()
                    ->fontFamily('mono')
                    ->weight(FontWeight::Bold)
                    ->description(fn (Coupon $record): ?string => $record->note),
                TextColumn::make('discount')->state(fn (Coupon $record): string => $record->describe()),
                TextColumn::make('status')->badge()->state(fn (Coupon $record): CouponStatus => $record->status()),
                TextColumn::make('window')->label('When it works')->state(fn (Coupon $record): string => static::window($record)),
                TextColumn::make('used')
                    ->label('Paid orders')
                    ->state(fn (Coupon $record): string => $record->max_orders === null ? (string) $record->orders_paid_count : "{$record->orders_paid_count} of {$record->max_orders}")
                    ->description(fn (Coupon $record): ?string => $record->orders_taken_count > $record->orders_paid_count ? ($record->orders_taken_count - $record->orders_paid_count).' paying now' : null)
                    ->alignEnd(),
                TextColumn::make('discount_given')
                    ->label('Taken off')
                    ->state(fn (Coupon $record): string => Money::of((int) $record->discount_given, $record->currency)->format())
                    ->alignEnd(),
            ])
            ->recordActions([
                ViewAction::make(),
                Action::make('orders')
                    ->label('Orders')
                    ->icon(Heroicon::OutlinedShoppingBag)
                    ->color('gray')
                    ->visible(fn (): bool => OrderResource::canViewAny())
                    ->url(fn (Coupon $record): string => OrderResource::getUrl('index', ['filters' => ['coupon_id' => ['value' => $record->id]]])),
                static::disableAction(),
                static::turnBackOnAction(),
            ])
            ->emptyStateIcon(Heroicon::OutlinedReceiptPercent)
            ->emptyStateHeading('No coupons yet')
            ->emptyStateDescription('Make one to share a discount, or to try a real purchase for as little as ₦100.');
    }

    private static function disableAction(): Action
    {
        return Action::make('disable')
            ->label('Disable')
            ->icon(Heroicon::OutlinedNoSymbol)
            ->color('danger')
            ->authorize('update')
            ->visible(fn (Coupon $record): bool => $record->disabled_at === null)
            ->requiresConfirmation()
            ->modalHeading(fn (Coupon $record): string => "Disable {$record->code}?")
            ->modalDescription('Nobody new can use it from now on. Anyone already paying with it keeps their discount until the hold on their tickets runs out.')
            ->modalSubmitActionLabel('Yes, disable it')
            ->action(function (Coupon $record): void {
                $record->forceFill(['disabled_at' => now()])->save();

                Notification::make()->title("{$record->code} is disabled")->success()->send();
            });
    }

    private static function turnBackOnAction(): Action
    {
        return Action::make('turnBackOn')
            ->label('Turn back on')
            ->icon(Heroicon::OutlinedArrowPath)
            ->color('gray')
            ->authorize('update')
            ->visible(fn (Coupon $record): bool => $record->disabled_at !== null)
            ->requiresConfirmation()
            ->modalHeading(fn (Coupon $record): string => "Turn {$record->code} back on?")
            ->modalDescription('Its terms stay as they were, and it works again as long as its dates and limits allow.')
            ->modalSubmitActionLabel('Yes, turn it back on')
            ->action(function (Coupon $record): void {
                $record->forceFill(['disabled_at' => null])->save();

                Notification::make()->title("{$record->code} is back on")->success()->send();
            });
    }

    /**
     * Whether the form is set to a percentage. Its state holds the enum at first and the plain value once it's changed.
     */
    private static function isPercentage(Get $get): bool
    {
        $type = $get('discount_type');

        return ($type instanceof CouponDiscountType ? $type : CouponDiscountType::tryFrom((string) $type)) === CouponDiscountType::Percentage;
    }

    /**
     * What's typed as it's stored: a percentage as it is, and naira in kobo.
     */
    private static function storedDiscount(string $typed, Get $get): int
    {
        return static::isPercentage($get) ? (int) $typed : (int) round(((float) $typed) * 100);
    }

    /**
     * Lines a field up with the label of the switch that reveals it.
     *
     * @return array<string, string>
     */
    private static function revealedFieldAttributes(): array
    {
        return ['style' => 'padding-inline-start: 3.25rem;'];
    }

    /**
     * The ticket the form's preview is worked out on.
     */
    private static function ticketToPreview(): ?TicketType
    {
        return once(fn (): ?TicketType => TicketType::query()->onSale()->orderBy('sort_order')->first() ?? TicketType::query()->orderBy('sort_order')->first());
    }

    /**
     * What one ticket comes to with the discount typed so far, or null until a usable amount is typed. It's priced by
     * the same code as a real order, so the preview can't disagree with the checkout.
     */
    private static function previewTotal(Get $get): ?Money
    {
        $typed = $get('discount_value');
        $ticketType = static::ticketToPreview();

        if (! is_numeric($typed) || (float) $typed <= 0 || $ticketType === null) {
            return null;
        }

        $coupon = new Coupon([
            'discount_type' => static::isPercentage($get) ? CouponDiscountType::Percentage : CouponDiscountType::FixedAmount,
            'discount_value' => static::storedDiscount((string) $typed, $get),
        ]);
        $price = $ticketType->unitPrice();

        return Money::of($price->amount - $coupon->discountFor($price, 1)->amount, $price->currency);
    }

    private static function previewHeading(Get $get): string
    {
        $total = static::previewTotal($get);

        return $total === null
            ? 'Type an amount to see what buyers will pay'
            : 'One '.static::ticketToPreview()->unitPrice()->format().' ticket comes to '.$total->format();
    }

    private static function previewDetail(Get $get): ?string
    {
        $total = static::previewTotal($get);

        return match (true) {
            $total === null => null,
            $total->amount <= config('payments.minimum_charge') => 'That’s the least a buyer can pay, because Bachs can’t charge less than '.Money::of(config('payments.minimum_charge'), $total->currency)->format().'.',
            static::isPercentage($get) => 'The percentage comes off every ticket it covers.',
            default => 'The amount comes off the order’s total, however many tickets they buy.',
        };
    }

    /**
     * When the coupon works, in words.
     */
    private static function window(Coupon $coupon): string
    {
        $format = fn (CarbonInterface $date): string => $date->timezone('Africa/Lagos')->format('j M Y, g:i A');

        return match (true) {
            $coupon->starts_at !== null && $coupon->ends_at !== null => $format($coupon->starts_at).' to '.$format($coupon->ends_at),
            $coupon->starts_at !== null => 'From '.$format($coupon->starts_at),
            $coupon->ends_at !== null => 'Until '.$format($coupon->ends_at),
            default => 'Any time',
        };
    }

    public static function getPages(): array
    {
        return [
            'index' => ManageCoupons::route('/'),
        ];
    }
}

<?php

namespace App\Filament\Resources\TrackedLinks;

use App\Enums\OrderStatus;
use App\Enums\TrackedLinkDestination;
use App\Enums\TrafficChannel;
use App\Filament\Resources\TrackedLinks\Pages\ManageTrackedLinks;
use App\Models\Order;
use App\Models\TrackedLink;
use App\Support\LinkQrCode;
use BackedEnum;
use Filament\Actions\Action;
use Filament\Actions\DeleteAction;
use Filament\Actions\EditAction;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Resources\Resource;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Components\Utilities\Set;
use Filament\Schemas\Schema;
use Filament\Support\Enums\FontWeight;
use Filament\Support\Enums\Width;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;
use UnitEnum;

/**
 * Short links for each place the tickets are shared. WhatsApp, Telegram, QR codes and typed addresses don't say
 * where a visit came from, so a link of its own is the only way to tell them apart in the reports.
 */
class TrackedLinkResource extends Resource
{
    protected static ?string $model = TrackedLink::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedLink;

    protected static string|UnitEnum|null $navigationGroup = 'Marketing';

    protected static ?int $navigationSort = 1;

    protected static ?string $modelLabel = 'tracked link';

    protected static ?string $recordTitleAttribute = 'name';

    public static function form(Schema $schema): Schema
    {
        return $schema
            ->columns(1)
            ->components([
                TextInput::make('name')
                    ->label('Name')
                    ->required()
                    ->maxLength(80)
                    ->placeholder('Instagram bio')
                    ->helperText('For you, to tell your links apart. Visitors never see it.')
                    ->live(onBlur: true)
                    ->afterStateUpdated(function (?string $state, string $operation, Get $get, Set $set): void {
                        if ($operation === 'create' && blank($get('slug'))) {
                            $set('slug', Str::limit(Str::slug((string) $state), 40, ''));
                        }
                    }),
                TextInput::make('slug')
                    ->label('Short address')
                    ->prefix(fn (): string => Str::after(url('/go'), '://').'/')
                    ->required()
                    ->maxLength(40)
                    ->regex('/^[a-z0-9]+(-[a-z0-9]+)*$/')
                    ->unique()
                    ->disabledOn('edit')
                    ->validationMessages([
                        'regex' => 'Use lowercase letters, numbers and single dashes, like ig-bio.',
                        'unique' => 'Another link already uses this address.',
                    ])
                    ->helperText('Short and easy to read out, like ig-bio. It can’t change once made, because the link may already be shared.'),
                Select::make('source')
                    ->label('Where it’s shared')
                    ->options(TrafficChannel::linkOptions())
                    ->required()
                    ->native(false),
                TextInput::make('medium')
                    ->label('What kind of post')
                    ->maxLength(40)
                    ->placeholder('story')
                    ->datalist(['bio', 'story', 'post', 'reel', 'status', 'group', 'broadcast', 'dm', 'ad', 'influencer', 'newsletter', 'poster'])
                    ->dehydrateStateUsing(fn (?string $state): ?string => filled($state) ? Str::slug($state) : null)
                    ->helperText('Optional. Tells apart two links on the same app, like a bio and a story.'),
                TextInput::make('campaign')
                    ->label('Campaign')
                    ->maxLength(60)
                    ->placeholder('black-friday')
                    ->dehydrateStateUsing(fn (?string $state): ?string => filled($state) ? Str::slug($state) : null)
                    ->helperText('Optional. Groups the links for one push, like early-bird or black-friday.'),
                Select::make('destination')
                    ->label('Where it goes')
                    ->options(TrackedLinkDestination::class)
                    ->default(TrackedLinkDestination::TicketPage)
                    ->required()
                    ->native(false),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->defaultSort('created_at', 'desc')
            ->columns([
                TextColumn::make('name')
                    ->searchable()
                    ->weight(FontWeight::SemiBold)
                    ->description(fn (TrackedLink $record): ?string => collect([$record->medium, $record->campaign])->filter()->join(' · ') ?: null),
                TextColumn::make('slug')
                    ->label('Short link')
                    ->searchable()
                    ->fontFamily('mono')
                    ->formatStateUsing(fn (TrackedLink $record): string => Str::after($record->shortUrl(), '://'))
                    ->copyable()
                    ->copyableState(fn (TrackedLink $record): string => $record->shortUrl())
                    ->copyMessage('Link copied')
                    ->description(fn (TrackedLink $record): string => 'Goes to '.Str::lcfirst($record->destination->getLabel())),
                TextColumn::make('source')
                    ->label('Shared on')
                    ->badge(),
                TextColumn::make('clicks')
                    ->label('Taps')
                    ->numeric()
                    ->sortable()
                    ->alignEnd()
                    ->description(fn (TrackedLink $record): ?string => $record->last_clicked_at ? 'Last '.$record->last_clicked_at->diffForHumans() : null),
                TextColumn::make('paid_orders')
                    ->label('Paid orders')
                    ->state(fn (TrackedLink $record): int => static::paidOrdersByLink()->get($record->slug, 0))
                    ->alignEnd(),
                TextColumn::make('created_at')
                    ->label('Made')
                    ->dateTime('j M Y', 'Africa/Lagos')
                    ->description(fn (TrackedLink $record): ?string => $record->creator?->name)
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->recordActions([
                Action::make('qrCode')
                    ->label('QR code')
                    ->icon(Heroicon::OutlinedQrCode)
                    ->color('gray')
                    ->modalHeading(fn (TrackedLink $record): string => "QR code for {$record->name}")
                    ->modalDescription('Put it on posters and flyers. Each scan counts as a tap on this link.')
                    ->modalContent(fn (TrackedLink $record): View => view('filament.tracked-links.qr-code', [
                        'shortUrl' => Str::after($record->shortUrl(), '://'),
                        'slug' => $record->slug,
                        'svg' => LinkQrCode::svg($record->shortUrl()),
                        'png' => base64_encode(LinkQrCode::png($record->shortUrl())),
                    ]))
                    ->modalWidth(Width::Medium)
                    ->modalSubmitAction(false)
                    ->modalCancelActionLabel('Close'),
                EditAction::make()
                    ->slideOver()
                    ->modalWidth(Width::ExtraLarge)
                    ->modalDescription('Changes apply to taps from now on. Visits already made stay credited as they were.'),
                DeleteAction::make()
                    ->modalDescription('Only links nobody has tapped yet can be deleted.'),
            ])
            ->emptyStateIcon(Heroicon::OutlinedLink)
            ->emptyStateHeading('No tracked links yet')
            ->emptyStateDescription('Make one for each place you share the tickets: your Instagram bio, a WhatsApp group, a poster. Then the dashboard can tell you which ones bring buyers.');
    }

    /**
     * Paid orders for each link, by its address. Orders keep the link's address in their tags, so this is one query
     * for the whole table.
     *
     * @return Collection<string, int>
     */
    private static function paidOrdersByLink(): Collection
    {
        return once(fn (): Collection => Order::query()
            ->where('status', OrderStatus::Paid)
            ->whereNotNull('attribution->content')
            ->pluck('attribution')
            ->countBy(fn (array $attribution): string => (string) $attribution['content']));
    }

    public static function getPages(): array
    {
        return [
            'index' => ManageTrackedLinks::route('/'),
        ];
    }
}

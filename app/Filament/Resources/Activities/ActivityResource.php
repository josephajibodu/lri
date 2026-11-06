<?php

namespace App\Filament\Resources\Activities;

use App\Filament\Resources\Activities\Pages\ManageActivities;
use App\Models\Activity;
use App\Models\AdminInvitation;
use App\Models\Coupon;
use App\Models\EmailToolSend;
use App\Models\Order;
use App\Models\PaymentChannel;
use App\Models\Ticket;
use App\Models\TicketType;
use App\Models\User;
use App\Models\WebhookCall;
use App\Reports\ReportPeriod;
use BackedEnum;
use Filament\Actions\ViewAction;
use Filament\Forms\Components\DatePicker;
use Filament\Infolists\Components\RepeatableEntry;
use Filament\Infolists\Components\RepeatableEntry\TableColumn;
use Filament\Infolists\Components\TextEntry;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\Column;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\Filter;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Carbon;
use Spatie\Permission\Models\Role;
use UnitEnum;

/**
 * The activity log: who changed what in the admin. Super admins can read it, and nobody can change it.
 */
class ActivityResource extends Resource
{
    protected static ?string $model = Activity::class;

    protected static ?string $slug = 'activity-log';

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedClipboardDocumentList;

    protected static string|UnitEnum|null $navigationGroup = 'System';

    protected static ?string $navigationLabel = 'Activity log';

    protected static ?string $modelLabel = 'activity';

    protected static ?string $pluralModelLabel = 'activity log';

    protected static ?int $navigationSort = 3;

    public static function infolist(Schema $schema): Schema
    {
        return $schema
            ->components([
                TextEntry::make('causer_name')->label('Who'),
                TextEntry::make('created_at')->label('When')->dateTime('j M Y, g:i:s A', 'Africa/Lagos'),
                TextEntry::make('description')->label('What they did')->columnSpanFull(),
                RepeatableEntry::make('changes')
                    ->label('What changed')
                    ->state(fn (Activity $record): array => $record->changeRows())
                    ->visible(fn (Activity $record): bool => $record->changeRows() !== [])
                    ->table([
                        TableColumn::make('Field'),
                        TableColumn::make('Before'),
                        TableColumn::make('After'),
                    ])
                    ->schema([
                        TextEntry::make('field'),
                        TextEntry::make('before'),
                        TextEntry::make('after'),
                    ])
                    ->columnSpanFull(),
            ])
            ->columns(2);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->defaultSort('id', 'desc')
            ->description('Everything people have changed in the admin, and who changed it. Only super admins can see this, and nobody can edit it.')
            ->emptyStateHeading('Nothing recorded yet')
            ->emptyStateDescription('Changes people make in the admin will appear here.')
            ->columns(static::logColumns())
            ->filters([
                SelectFilter::make('causer_id')
                    ->label('Who')
                    ->options(fn (): array => Activity::query()
                        ->whereNotNull('causer_id')
                        ->orderBy('causer_name')
                        ->pluck('causer_name', 'causer_id')
                        ->all()),
                SelectFilter::make('subject_type')
                    ->label('Part of the admin')
                    ->options([
                        Order::class => 'Orders',
                        Ticket::class => 'Tickets',
                        TicketType::class => 'Ticket types',
                        Coupon::class => 'Coupons',
                        PaymentChannel::class => 'Ways to pay',
                        User::class => 'People',
                        AdminInvitation::class => 'Invitations',
                        Role::class => 'Roles',
                        WebhookCall::class => 'Webhooks',
                        EmailToolSend::class => 'Email tool',
                    ]),
                static::periodFilter(),
            ])
            ->recordActions([
                ViewAction::make(),
            ]);
    }

    /**
     * The log's columns, which the dashboard's recent activity shows too.
     *
     * @return list<Column>
     */
    public static function logColumns(): array
    {
        return [
            TextColumn::make('created_at')->label('When')->dateTime('j M, g:i A', 'Africa/Lagos')->sortable(),
            TextColumn::make('causer_name')->label('Who')->searchable(),
            TextColumn::make('description')
                ->label('What they did')
                ->description(fn (Activity $record): ?string => $record->changeSummary())
                ->searchable()
                ->wrap(),
        ];
    }

    /**
     * Dates here mean days in Lagos, as everywhere else in the admin, while the log's times are saved in UTC.
     */
    private static function periodFilter(): Filter
    {
        return Filter::make('period')
            ->schema([
                DatePicker::make('from'),
                DatePicker::make('until'),
            ])
            ->columns(2)
            ->query(fn (Builder $query, array $data): Builder => ReportPeriod::fromDates($data['from'] ?? null, $data['until'] ?? null)->apply($query, 'created_at'))
            ->indicateUsing(fn (array $data): array => array_values(array_filter([
                filled($data['from'] ?? null) ? 'From '.Carbon::parse($data['from'])->format('j M Y') : null,
                filled($data['until'] ?? null) ? 'Until '.Carbon::parse($data['until'])->format('j M Y') : null,
            ])));
    }

    public static function getPages(): array
    {
        return [
            'index' => ManageActivities::route('/'),
        ];
    }
}

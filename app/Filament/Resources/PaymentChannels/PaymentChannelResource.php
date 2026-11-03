<?php

namespace App\Filament\Resources\PaymentChannels;

use App\Enums\PaymentMethod;
use App\Filament\Resources\PaymentChannels\Pages\ManagePaymentChannels;
use App\Models\PaymentChannel;
use BackedEnum;
use Filament\Actions\EditAction;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Columns\ToggleColumn;
use Filament\Tables\Table;
use Illuminate\Validation\Rules\Unique;
use UnitEnum;

/**
 * The ways to pay the checkout offers: switch them on or off, feature them up front, and set their order.
 */
class PaymentChannelResource extends Resource
{
    protected static ?string $model = PaymentChannel::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedCreditCard;

    protected static string|UnitEnum|null $navigationGroup = 'Settings';

    protected static ?string $navigationLabel = 'Ways to pay';

    protected static ?string $modelLabel = 'way to pay';

    protected static ?string $pluralModelLabel = 'ways to pay';

    protected static ?int $navigationSort = 1;

    public static function form(Schema $schema): Schema
    {
        return $schema
            ->components([
                Select::make('method')
                    ->options(PaymentMethod::class)
                    ->required()
                    ->disabledOn('edit'),
                TextInput::make('currency')
                    ->default('NGN')
                    ->required()
                    ->length(3)
                    ->disabledOn('edit')
                    ->unique(ignoreRecord: true, modifyRuleUsing: fn (Unique $rule, callable $get) => $rule->where('method', $get('method'))),
                Select::make('provider')
                    ->options(['bachs' => 'Bachs'])
                    ->default('bachs')
                    ->required()
                    ->helperText('The payment company that handles this method.'),
                TextInput::make('label')
                    ->maxLength(60)
                    ->placeholder(fn (callable $get): ?string => PaymentMethod::tryFrom((string) $get('method'))?->label())
                    ->helperText('What buyers see. Leave empty to use the method’s name.'),
                TextInput::make('description')
                    ->maxLength(120)
                    ->helperText('A short line under the button, such as “Send from any Nigerian bank app.”')
                    ->columnSpanFull(),
                Toggle::make('is_enabled')->label('Offered at checkout')->default(true),
                Toggle::make('is_featured')->label('Featured up front')->helperText('Featured methods show as big “Pay with” buttons; the rest fold under “More ways to pay”.'),
            ])
            ->columns(2);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->reorderable('sort_order')
            ->defaultSort('sort_order')
            ->description('Drag to set the order buyers see. Run php artisan payments:sync-channels to pick up methods newly switched on at the provider.')
            ->columns([
                TextColumn::make('display_label')->label('Shown as')->description(fn (PaymentChannel $channel): ?string => $channel->description),
                TextColumn::make('method')->badge()->color('gray'),
                TextColumn::make('currency'),
                TextColumn::make('provider'),
                ToggleColumn::make('is_enabled')->label('Offered'),
                ToggleColumn::make('is_featured')->label('Featured'),
            ])
            ->recordActions([
                EditAction::make(),
            ]);
    }

    public static function getPages(): array
    {
        return [
            'index' => ManagePaymentChannels::route('/'),
        ];
    }
}

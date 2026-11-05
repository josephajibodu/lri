<?php

namespace App\Filament\Resources\Users;

use App\Enums\StaffRole;
use App\Filament\Resources\Users\Pages\ManageUsers;
use App\Models\User;
use BackedEnum;
use Closure;
use Filament\Actions\DeleteAction;
use Filament\Actions\EditAction;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Spatie\Permission\Models\Role;
use UnitEnum;

/**
 * The people who can sign in to the admin, and the roles that decide what each of them sees.
 */
class UserResource extends Resource
{
    protected static ?string $model = User::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedUsers;

    protected static string|UnitEnum|null $navigationGroup = 'Settings';

    protected static ?string $navigationLabel = 'People';

    protected static ?string $modelLabel = 'person';

    protected static ?string $pluralModelLabel = 'people';

    protected static ?int $navigationSort = 2;

    protected static ?string $recordTitleAttribute = 'name';

    public static function form(Schema $schema): Schema
    {
        return $schema
            ->components([
                TextInput::make('name')->required()->maxLength(255),
                TextInput::make('email')->email()->required()->maxLength(255)->unique(ignoreRecord: true),
                Select::make('roles')
                    ->relationship(
                        'roles',
                        'name',
                        modifyQueryUsing: fn (Builder $query): Builder => static::assignableRoles($query),
                    )
                    ->multiple()
                    ->preload()
                    ->required()
                    ->rule(fn (): Closure => static::onlyAssignableRoles())
                    ->disabled(fn (?User $record): bool => $record?->is(auth()->user()) ?? false)
                    ->helperText(fn (?User $record): string => $record?->is(auth()->user())
                        ? 'You can’t change your own roles. Ask another admin.'
                        : 'Decides which parts of the admin they see. Set up roles under Settings > Roles.'),
                TextInput::make('password')
                    ->password()
                    ->revealable()
                    ->minLength(12)
                    ->required(fn (string $operation): bool => $operation === 'create')
                    ->dehydrated(fn (?string $state): bool => filled($state))
                    ->helperText(fn (string $operation): ?string => $operation === 'edit' ? 'Leave empty to keep the current password.' : 'At least 12 characters.'),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('name')->searchable(),
                TextColumn::make('email')->searchable(),
                TextColumn::make('roles.name')->label('Roles')->badge(),
                TextColumn::make('created_at')->label('Added')->date('j M Y'),
            ])
            ->recordActions([
                EditAction::make(),
                DeleteAction::make(),
            ]);
    }

    /**
     * The roles the signed-in person may give out. Only a super admin can make someone else a super admin.
     *
     * @param  Builder<Role>  $query
     * @return Builder<Role>
     */
    public static function assignableRoles(Builder $query): Builder
    {
        return $query->when(
            ! auth()->user()?->isSuperAdmin(),
            fn (Builder $query): Builder => $query->where('name', '!=', StaffRole::SuperAdmin->value),
        );
    }

    /**
     * A validation rule refusing any role the signed-in person may not give out.
     */
    public static function onlyAssignableRoles(): Closure
    {
        return function (string $attribute, mixed $value, Closure $fail): void {
            $offered = static::assignableRoles(Role::query())->pluck('id')->all();

            if (array_diff(array_map('intval', (array) $value), $offered) !== []) {
                $fail('You can’t give out that role.');
            }
        };
    }

    public static function getPages(): array
    {
        return [
            'index' => ManageUsers::route('/'),
        ];
    }
}

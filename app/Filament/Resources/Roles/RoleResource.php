<?php

namespace App\Filament\Resources\Roles;

use App\Enums\AdminPermission;
use App\Enums\StaffRole;
use App\Filament\Resources\Roles\Pages\ManageRoles;
use BackedEnum;
use Filament\Actions\DeleteAction;
use Filament\Actions\EditAction;
use Filament\Forms\Components\CheckboxList;
use Filament\Forms\Components\TextInput;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Validation\Rules\Unique;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;
use UnitEnum;

/**
 * Roles: named sets of the parts of the admin someone can see. The super admin role sees everything and can't be
 * changed.
 */
class RoleResource extends Resource
{
    protected static ?string $model = Role::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedShieldCheck;

    protected static string|UnitEnum|null $navigationGroup = 'Settings';

    protected static ?int $navigationSort = 3;

    protected static ?string $recordTitleAttribute = 'name';

    public static function form(Schema $schema): Schema
    {
        return $schema
            ->components([
                TextInput::make('name')
                    ->required()
                    ->maxLength(100)
                    ->unique(ignoreRecord: true, modifyRuleUsing: fn (Unique $rule): Unique => $rule->where('guard_name', 'web')),
                CheckboxList::make('permissions')
                    ->label('What this role can see')
                    ->relationship(
                        'permissions',
                        'name',
                        modifyQueryUsing: fn (Builder $query): Builder => $query->whereIn('name', array_column(AdminPermission::cases(), 'value')),
                    )
                    ->getOptionLabelFromRecordUsing(fn (Permission $record): string => AdminPermission::from($record->name)->getLabel())
                    ->descriptions(fn (): array => Permission::query()
                        ->whereIn('name', array_column(AdminPermission::cases(), 'value'))
                        ->pluck('name', 'id')
                        ->map(fn (string $name): string => AdminPermission::from($name)->getDescription())
                        ->all())
                    ->columns(2)
                    ->gridDirection('row')
                    ->bulkToggleable()
                    ->columnSpanFull(),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->modifyQueryUsing(fn (Builder $query): Builder => $query->with('permissions')->withCount('users'))
            ->columns([
                TextColumn::make('name')->searchable()->weight('bold'),
                TextColumn::make('permissions')
                    ->label('Can see')
                    ->badge()
                    ->state(fn (Role $record): array => $record->name === StaffRole::SuperAdmin->value
                        ? ['Everything']
                        : $record->permissions
                            ->map(fn (Permission $permission): ?string => AdminPermission::tryFrom($permission->name)?->getLabel())
                            ->filter()
                            ->values()
                            ->all())
                    ->placeholder('Nothing yet'),
                TextColumn::make('users_count')->label('People'),
            ])
            ->recordActions([
                EditAction::make()->after(fn () => static::forgetCachedPermissions()),
                DeleteAction::make(),
            ]);
    }

    /**
     * Spatie keeps each role's permissions in the cache; saving the checkboxes changes them behind its back.
     */
    public static function forgetCachedPermissions(): void
    {
        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }

    public static function getPages(): array
    {
        return [
            'index' => ManageRoles::route('/'),
        ];
    }
}

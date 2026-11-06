<?php

namespace App\Filament\Resources\Roles\Pages;

use App\Filament\Resources\Roles\RoleResource;
use App\Support\AdminActivity;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ManageRecords;
use Spatie\Permission\Models\Role;

class ManageRoles extends ManageRecords
{
    protected static string $resource = RoleResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make()
                ->mutateDataUsing(fn (array $data): array => [...$data, 'guard_name' => 'web'])
                ->after(function (Role $record): void {
                    RoleResource::forgetCachedPermissions();
                    AdminActivity::recordChange("Created role \"{$record->name}\"", $record, [], RoleResource::whatTheLogRecords($record));
                }),
        ];
    }
}

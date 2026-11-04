<?php

use App\Enums\AdminPermission;
use App\Enums\StaffRole;
use Illuminate\Database\Migrations\Migration;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;

return new class extends Migration
{
    /**
     * Create a permission for each part of the admin and the roles the site starts with.
     */
    public function up(): void
    {
        app(PermissionRegistrar::class)->forgetCachedPermissions();

        foreach (AdminPermission::cases() as $permission) {
            Permission::findOrCreate($permission->value, 'web');
        }

        Role::findOrCreate(StaffRole::SuperAdmin->value, 'web');
        Role::findOrCreate(StaffRole::Admin->value, 'web')->syncPermissions(array_column(AdminPermission::cases(), 'value'));
        Role::findOrCreate(StaffRole::HeadsetDesk->value, 'web')->syncPermissions([AdminPermission::UseHeadsetDesk->value]);

        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Role::query()->whereIn('name', array_column(StaffRole::cases(), 'value'))->delete();
        Permission::query()->whereIn('name', array_column(AdminPermission::cases(), 'value'))->delete();

        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }
};

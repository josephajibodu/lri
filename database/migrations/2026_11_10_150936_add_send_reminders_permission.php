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
     * Create the permission for the abandoned orders section, where reminders are emailed, and tick it for the Admin role, which sees every section.
     */
    public function up(): void
    {
        app(PermissionRegistrar::class)->forgetCachedPermissions();

        $permission = Permission::findOrCreate(AdminPermission::SendReminders->value, 'web');

        Role::query()->where('name', StaffRole::Admin->value)->first()?->givePermissionTo($permission);

        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Permission::query()->where('name', AdminPermission::SendReminders->value)->delete();

        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }
};

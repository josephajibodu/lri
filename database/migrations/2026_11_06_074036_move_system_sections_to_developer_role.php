<?php

use App\Enums\AdminPermission;
use App\Enums\StaffRole;
use Illuminate\Database\Migrations\Migration;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;

return new class extends Migration
{
    /**
     * Everything under System goes to a new Developer role and comes off the Admin role. Other roles keep what's
     * ticked on them, because someone chose to give them those sections.
     */
    public function up(): void
    {
        app(PermissionRegistrar::class)->forgetCachedPermissions();

        Role::findOrCreate(StaffRole::Developer->value, 'web')->givePermissionTo($this->systemPermissions());
        Role::query()->where('name', StaffRole::Admin->value)->first()?->revokePermissionTo($this->systemPermissions());

        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Role::query()->where('name', StaffRole::Admin->value)->first()?->givePermissionTo($this->systemPermissions());
        Role::query()->where('name', StaffRole::Developer->value)->delete();

        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }

    /**
     * @return list<string>
     */
    private function systemPermissions(): array
    {
        return [
            AdminPermission::ManageWebhooks->value,
            AdminPermission::ManageEmailTool->value,
            AdminPermission::ViewLogs->value,
        ];
    }
};

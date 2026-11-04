<?php

namespace App\Providers;

use App\EmailTools\EmailToolManager;
use App\Enums\AdminPermission;
use App\Models\User;
use App\Payments\PaymentManager;
use App\Policies\RolePolicy;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\URL;
use Illuminate\Support\ServiceProvider;
use Spatie\Permission\Models\Role;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        $this->app->singleton(PaymentManager::class);
        $this->app->singleton(EmailToolManager::class);
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        if ($this->app->isProduction()) {
            URL::forceScheme('https');
        }

        Gate::policy(Role::class, RolePolicy::class);

        /*
         * Super admins hold every admin permission, including ones added later. This only answers the permission
         * checks themselves, so rules on top of them (such as nobody removing themselves) still apply.
         */
        Gate::before(fn (User $user, string $ability): ?bool => $user->isSuperAdmin() && AdminPermission::tryFrom($ability) ? true : null);
        Gate::define('viewLogViewer', fn (?User $user): bool => $user?->can(AdminPermission::ViewLogs->value) ?? false);
    }
}

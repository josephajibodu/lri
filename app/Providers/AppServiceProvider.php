<?php

namespace App\Providers;

use App\EmailTools\EmailToolManager;
use App\Enums\AdminPermission;
use App\Models\User;
use App\Payments\PaymentManager;
use App\Policies\RolePolicy;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\RateLimiter;
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

        $this->configureRateLimits();
    }

    /**
     * Give each public action its own count of how often a visitor used it in the last minute. A limit written straight
     * on a route, like throttle:10,1, adds to one count per visitor that all such routes share, so an order page checking
     * on its payment would use up the tries for "Pay now".
     */
    private function configureRateLimits(): void
    {
        $requestsPerMinute = [
            'birthday-submissions' => 10,
            'barracks-registrations' => 10,
            'barracks-passports' => 20,
            'order-placements' => 10,
            'checkout-events' => 60,
            'order-status-checks' => 30,
            'order-payments' => 10,
            'ticket-changes' => 20,
            'ticket-claims' => 10,
            'invitation-pages' => 30,
            'invitation-answers' => 10,
            'payment-webhooks' => 240,
        ];

        foreach ($requestsPerMinute as $limiter => $limit) {
            RateLimiter::for($limiter, fn (Request $request): Limit => Limit::perMinute($limit)->by($request->ip()));
        }
    }
}

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
     * Each step of buying a ticket has its own counter, because a plain throttle:10,1 shares one counter with every other
     * such route. Otherwise the order page checking on a payment every few seconds would lock its buyer out of paying.
     * Steps tied to one order or ticket count per address, since people on one mobile network can share an IP address.
     */
    private function defineBuyingRateLimits(): void
    {
        $perPageAndAddress = fn (Request $request): string => $request->path().'|'.$request->ip();

        RateLimiter::for('orders', fn (Request $request): array => [
            Limit::perMinute(30)->by('address:'.$request->ip()),
            Limit::perMinute(5)->by('email:'.(is_string($request->input('email')) ? strtolower(trim($request->input('email'))) : '')),
        ]);
        RateLimiter::for('coupon-checks', fn (Request $request): Limit => Limit::perMinute(10)->by($request->ip()));
        RateLimiter::for('checkout-events', fn (Request $request): Limit => Limit::perMinute(60)->by($request->ip()));
        RateLimiter::for('order-pages', fn (Request $request): Limit => Limit::perMinute(60)->by($perPageAndAddress($request)));
        RateLimiter::for('order-status', fn (Request $request): Limit => Limit::perMinute(30)->by($perPageAndAddress($request)));
        RateLimiter::for('order-payments', fn (Request $request): Limit => Limit::perMinute(10)->by($perPageAndAddress($request)));
        RateLimiter::for('ticket-claims', fn (Request $request): Limit => Limit::perMinute(10)->by($perPageAndAddress($request)));
        RateLimiter::for('payment-webhooks', fn (Request $request): Limit => Limit::perMinute(240)->by($request->ip()));

        /* Per receipt, not per ticket: naming a ticket emails someone, and one receipt can have many tickets. */
        RateLimiter::for('ticket-changes', fn (Request $request): Limit => Limit::perMinute(20)->by((string) $request->segment(2)));
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

        $this->defineBuyingRateLimits();

        /*
         * Super admins hold every admin permission, including ones added later. This only answers the permission
         * checks themselves, so rules on top of them (such as nobody removing themselves) still apply.
         */
        Gate::before(fn (User $user, string $ability): ?bool => $user->isSuperAdmin() && AdminPermission::tryFrom($ability) ? true : null);
        Gate::define('viewLogViewer', fn (?User $user): bool => $user?->can(AdminPermission::ViewLogs->value) ?? false);
    }
}

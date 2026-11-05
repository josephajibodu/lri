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
     * on its payment would use up the tries for "Pay now". Steps tied to one order or ticket count per address and page,
     * since people on one mobile network can share an address.
     */
    private function configureRateLimits(): void
    {
        $perAddress = [
            'birthday-submissions' => 10,
            'barracks-registrations' => 10,
            'barracks-passports' => 20,
            'coupon-checks' => 10,
            'checkout-events' => 60,
            'invitation-pages' => 30,
            'invitation-answers' => 10,
            'payment-webhooks' => 240,
        ];

        foreach ($perAddress as $limiter => $limit) {
            RateLimiter::for($limiter, fn (Request $request): Limit => Limit::perMinute($limit)->by($request->ip()));
        }

        $perAddressAndPage = [
            'order-pages' => 60,
            'order-status-checks' => 30,
            'order-payments' => 10,
            'ticket-claims' => 10,
        ];

        foreach ($perAddressAndPage as $limiter => $limit) {
            RateLimiter::for($limiter, fn (Request $request): Limit => Limit::perMinute($limit)->by($request->path().'|'.$request->ip()));
        }

        /* A shared address gets plenty of room for separate buyers, and one email gets much less. */
        RateLimiter::for('order-placements', function (Request $request): array {
            $email = $request->input('email');

            return array_filter([
                Limit::perMinute(30)->by('address:'.$request->ip()),
                is_string($email) && $email !== '' ? Limit::perMinute(5)->by('email:'.strtolower(trim($email))) : null,
            ]);
        });

        /* Per receipt, not per ticket: naming a ticket emails someone, and one receipt can have many tickets. */
        RateLimiter::for('ticket-changes', fn (Request $request): Limit => Limit::perMinute(20)->by((string) $request->segment(2)));
    }
}

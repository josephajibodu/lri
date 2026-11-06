<?php

namespace App\Providers\Filament;

use App\Enums\AdminPermission;
use App\Filament\Pages\Auth\Login;
use Filament\Http\Middleware\Authenticate;
use Filament\Http\Middleware\AuthenticateSession;
use Filament\Http\Middleware\DisableBladeIconComponents;
use Filament\Http\Middleware\DispatchServingFilamentEvent;
use Filament\Navigation\NavigationGroup;
use Filament\Navigation\NavigationItem;
use Filament\Panel;
use Filament\PanelProvider;
use Filament\Support\Colors\Color;
use Filament\Support\Icons\Heroicon;
use Filament\View\PanelsRenderHook;
use Illuminate\Contracts\View\View;
use Illuminate\Cookie\Middleware\AddQueuedCookiesToResponse;
use Illuminate\Cookie\Middleware\EncryptCookies;
use Illuminate\Foundation\Http\Middleware\PreventRequestForgery;
use Illuminate\Routing\Middleware\SubstituteBindings;
use Illuminate\Session\Middleware\StartSession;
use Illuminate\View\Middleware\ShareErrorsFromSession;

class AdminPanelProvider extends PanelProvider
{
    /**
     * LRI orange (#FFB000) is shade 400, because Filament puts light-mode buttons on 400 and their hovers on 300. The
     * tints and the gold (700, for yellow text that's readable on a light background) come from the site's own colours.
     * The darkest shades are the site's ink, which is what Filament then uses for the text on those buttons.
     *
     * @var array<int, string>
     */
    private const array BRAND_YELLOW = [
        50 => '#FFF9E8',
        100 => '#FFF1CC',
        200 => '#FFE28A',
        300 => '#FFC83D',
        400 => '#FFB000',
        500 => '#E09C00',
        600 => '#B87F00',
        700 => '#8A5A00',
        800 => '#1C1917',
        900 => '#1C1917',
        950 => '#1C1917',
    ];

    public function panel(Panel $panel): Panel
    {
        return $panel
            ->default()
            ->id('admin')
            ->path('admin')
            ->login(Login::class)
            ->renderHook(PanelsRenderHook::HEAD_END, fn (): View => view('filament.brand-styles'))
            ->renderHook(PanelsRenderHook::SIMPLE_PAGE_START, fn (): View => view('filament.auth.login-logo'), scopes: Login::class)
            ->renderHook(PanelsRenderHook::AUTH_LOGIN_FORM_AFTER, fn (): View => view('filament.auth.login-back-link'), scopes: Login::class)
            ->brandName('LRI Admin')
            ->brandLogo(asset('images/lri-logo-email.png'))
            ->darkModeBrandLogo(asset('images/lri-logo-on-dark.png'))
            ->brandLogoHeight('2.5rem')
            ->favicon(asset('favicon.svg'))
            ->colors([
                'primary' => self::BRAND_YELLOW,
                'gray' => Color::Stone,
            ])
            ->navigationGroups([
                NavigationGroup::make('Tickets'),
                NavigationGroup::make('Club'),
                NavigationGroup::make('Settings'),
                NavigationGroup::make('System'),
            ])
            ->navigationItems([
                NavigationItem::make('Logs')
                    ->url(fn (): string => route('log-viewer.index'))
                    ->visible(fn (): bool => auth()->user()?->can(AdminPermission::ViewLogs->value) ?? false)
                    ->openUrlInNewTab()
                    ->icon(Heroicon::OutlinedDocumentMagnifyingGlass)
                    ->group('System')
                    ->sort(10),
            ])
            ->discoverResources(in: app_path('Filament/Resources'), for: 'App\Filament\Resources')
            ->discoverPages(in: app_path('Filament/Pages'), for: 'App\Filament\Pages')
            ->pages([])
            ->discoverWidgets(in: app_path('Filament/Widgets'), for: 'App\Filament\Widgets')
            ->widgets([])
            ->middleware([
                EncryptCookies::class,
                AddQueuedCookiesToResponse::class,
                StartSession::class,
                AuthenticateSession::class,
                ShareErrorsFromSession::class,
                PreventRequestForgery::class,
                SubstituteBindings::class,
                DisableBladeIconComponents::class,
                DispatchServingFilamentEvent::class,
            ])
            ->authMiddleware([
                Authenticate::class,
            ]);
    }
}

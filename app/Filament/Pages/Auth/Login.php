<?php

namespace App\Filament\Pages\Auth;

use Filament\Auth\Pages\Login as BaseLogin;
use Filament\Schemas\Components\Component;

/**
 * The admin's sign-in page, with the club's logo and a way back to the website, because anyone who scans a ticket's QR
 * code lands here. The logo and link are added in AdminPanelProvider, for this page only.
 */
class Login extends BaseLogin
{
    public function getTitle(): string
    {
        return 'Sign in';
    }

    public function getHeading(): string
    {
        return 'Welcome back';
    }

    public function getSubheading(): string
    {
        return 'Sign in to the '.config('lri.site.name').' admin.';
    }

    /**
     * The club's logo takes the place of the panel's name at the top of the card.
     */
    public function hasLogo(): bool
    {
        return false;
    }

    /**
     * Both fields are still required. They just don't need an asterisk when they're the only two on the form.
     */
    protected function getEmailFormComponent(): Component
    {
        return parent::getEmailFormComponent()->markAsRequired(false);
    }

    protected function getPasswordFormComponent(): Component
    {
        return parent::getPasswordFormComponent()->markAsRequired(false);
    }
}

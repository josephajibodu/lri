<?php

namespace App\Http\Middleware;

use Closure;
use Filament\Facades\Filament;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Refuse /admin for someone with an empty menu, such as desk staff while the desk is hidden. Filament sends /admin to
 * the first section in the menu, and with no sections it sends them back to /admin until the browser gives up.
 */
class EnsureAdminHomeHasSection
{
    /**
     * Handle an incoming request.
     *
     * @param  Closure(Request): (Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        abort_if(
            $request->routeIs('filament.admin.home') && Filament::getCurrentOrDefaultPanel()->getRedirectUrl() === $request->url(),
            403,
        );

        return $next($request);
    }
}

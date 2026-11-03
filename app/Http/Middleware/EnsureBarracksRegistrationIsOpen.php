<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Answer "not found" for every barracks page while the registration is switched off in config/lri.php.
 */
class EnsureBarracksRegistrationIsOpen
{
    /**
     * Handle an incoming request.
     *
     * @param  Closure(Request): (Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        abort_unless(config('lri.barracks_registration.open'), 404);

        return $next($request);
    }
}

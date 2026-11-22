<?php

namespace App\Http\Middleware;

use App\Filament\Pages\HeadsetDesk;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Keep the check-in scanner to the people who can use the headset desk. Anyone else who has its link, signed in or
 * not, is sent to the website's home page, so the page gives nothing away. The scanner's own requests are refused
 * outright instead, so it can tell staff they've been signed out.
 */
class EnsureCanCheckTicketsIn
{
    /**
     * Handle an incoming request.
     *
     * @param  Closure(Request): (Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        if (HeadsetDesk::canAccess()) {
            return $next($request);
        }

        abort_if($request->expectsJson(), 403);

        return redirect()->route('home');
    }
}

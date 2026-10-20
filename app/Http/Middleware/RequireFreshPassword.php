<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * People signed in with a temporary password must pick their own
 * before they can use the rest of Cloyne.
 */
class RequireFreshPassword
{
    public function handle(Request $request, Closure $next): Response
    {
        if ($request->user()?->must_change_password && ! $request->routeIs('account', 'account.*', 'logout')) {
            return redirect()->route('account')->with('status', 'Choose your own password to continue.');
        }

        return $next($request);
    }
}

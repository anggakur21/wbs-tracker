<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/** Someone signed in with a one-time password must choose their own before doing anything else. */
class RequirePasswordChange
{
    public function handle(Request $request, Closure $next): Response
    {
        if ($request->user()?->must_change_password && ! $request->routeIs('account.password', 'account.password.update', 'logout')) {
            return redirect()->route('account.password');
        }

        return $next($request);
    }
}

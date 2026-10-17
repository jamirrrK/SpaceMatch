<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\Response;

class EnsureUserHasRole
{
    /**
     * Handle an incoming request.
     */
    public function handle(Request $request, Closure $next, string ...$roles): Response
    {
        if (! Auth::check()) {
            return redirect()->route('login');
        }

        $userRole = $request->user()?->role;

        if (empty($roles) || ! in_array($userRole, $roles, true)) {
            abort(403, 'You do not have access to this page.');
        }

        return $next($request);
    }
}

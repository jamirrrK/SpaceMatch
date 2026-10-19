<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureAdministratorPasswordChanged
{
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        if ($user?->isAdministrator()
            && $user->password_change_required
            && ! $request->routeIs('admin.security.password.*', 'logout')) {
            return redirect()->route('admin.security.password.edit');
        }

        return $next($request);
    }
}

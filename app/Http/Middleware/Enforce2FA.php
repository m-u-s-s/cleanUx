<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use Symfony\Component\HttpFoundation\Response;

/**
 * Enforce two-factor authentication for platform administrators.
 *
 * Redirects to profile.edit if the admin has not yet confirmed 2FA setup.
 */
class Enforce2FA
{
    public function handle(Request $request, Closure $next): Response
    {
        if (! config('auth.enforce_2fa_for_admins', false)) {
            return $next($request);
        }

        $user = $request->user();

        if ($user && $user->isAdmin() && ! $user->two_factor_confirmed_at) {
            $route = Route::has('profile.show') ? 'profile.show' : 'dashboard';

            return redirect()->route($route)
                ->with('warning', "Veuillez activer l'authentification à deux facteurs.");
        }

        return $next($request);
    }
}

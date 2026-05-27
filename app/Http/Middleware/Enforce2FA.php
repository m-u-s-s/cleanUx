<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
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
        $user = $request->user();

        if ($user && $user->isAdmin() && ! $user->two_factor_confirmed_at) {
            return redirect()->route('profile.edit')
                ->with('warning', "Veuillez activer l'authentification à deux facteurs.");
        }

        return $next($request);
    }
}

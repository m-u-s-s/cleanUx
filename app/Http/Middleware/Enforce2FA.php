<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use Symfony\Component\HttpFoundation\Response;

/**
 * Enforce two-factor authentication for platform administrators.
 *
 * When an admin has not yet confirmed a 2FA setup, requests are redirected to
 * the profile page where 2FA is enabled. The setup, confirmation and logout
 * endpoints are exempted so the admin can actually complete enrolment (and
 * leave) without the redirect chasing its own tail.
 */
class Enforce2FA
{
    /**
     * Route names the un-enrolled admin must still reach, otherwise the
     * redirect below would loop onto the very page that sets up 2FA.
     */
    private const EXEMPT_ROUTES = [
        'profile.show',
        'logout',
    ];

    /** Route-name prefixes that are always allowed (Fortify 2FA endpoints). */
    private const EXEMPT_PREFIXES = [
        'two-factor.',
    ];

    public function handle(Request $request, Closure $next): Response
    {
        if (! config('auth.enforce_2fa_for_admins', false)) {
            return $next($request);
        }

        $user = $request->user();

        if ($user && $user->isAdmin() && ! $user->two_factor_confirmed_at) {
            if ($this->isExempt($request)) {
                return $next($request);
            }

            // Fall back to a route that is NOT itself behind this middleware,
            // so a missing profile.show can never produce a redirect loop
            // (dashboard → admin.dashboard → enforce_2fa → …).
            $target = Route::has('profile.show') ? 'profile.show' : 'home';

            if (! Route::has($target)) {
                // Last-resort: no safe redirect target — surface a clear 403
                // rather than risk a loop or a RouteNotFoundException.
                abort(403, "Authentification à deux facteurs requise pour l'espace admin.");
            }

            return redirect()->route($target)
                ->with('warning', "Veuillez activer l'authentification à deux facteurs avant d'accéder à l'espace admin.");
        }

        return $next($request);
    }

    private function isExempt(Request $request): bool
    {
        $name = $request->route()?->getName();

        if ($name === null) {
            return false;
        }

        if (in_array($name, self::EXEMPT_ROUTES, true)) {
            return true;
        }

        foreach (self::EXEMPT_PREFIXES as $prefix) {
            if (str_starts_with($name, $prefix)) {
                return true;
            }
        }

        return false;
    }
}

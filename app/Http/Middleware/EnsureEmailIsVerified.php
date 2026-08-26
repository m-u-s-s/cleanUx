<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Redirect;
use Illuminate\Support\Facades\URL;
use Symfony\Component\HttpFoundation\Response;

/**
 * Exige une adresse e-mail confirmée — sur les DEUX surfaces, par le même alias.
 *
 * Celui de Laravel répond `abort(403)` en JSON : un message anglais, sans code. L'application
 * ne pouvait pas distinguer ce refus d'un autre, donc pas dresser l'écran qui le résout.
 */
class EnsureEmailIsVerified
{
    /** Conservé pour la forme `EnsureEmailIsVerified::redirectTo('route')` du cadre. */
    public static function redirectTo(string $route): string
    {
        return static::class.':'.$route;
    }

    public function handle(Request $request, Closure $next, ?string $redirectToRoute = null): Response
    {
        // `$request->user()` est annoté `User`, qui implémente MustVerifyEmail : tester le
        // contrat serait une branche morte, et PHPStan le dit.
        if ($request->user()?->hasVerifiedEmail()) {
            return $next($request);
        }

        if ($request->expectsJson()) {
            return response()->json([
                'ok' => false,
                'error_code' => 'email_non_verifie',
                'message' => __("Votre adresse e-mail n'est pas confirmée."),
            ], 403);
        }

        return Redirect::guest(URL::route($redirectToRoute ?: 'verification.notice'));
    }
}

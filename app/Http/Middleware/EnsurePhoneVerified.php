<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/** OTP téléphone — garde optionnelle (gated) : quand auth.require_phone_verification est activé, force la vérification du numéro avant d'accéder aux routes protégées. */
class EnsurePhoneVerified
{
    public function handle(Request $request, Closure $next): Response
    {
        if (! (bool) config('auth.require_phone_verification', false)) {
            return $next($request);
        }

        $user = $request->user();

        if ($user && ! $user->hasVerifiedPhone() && ! $request->routeIs('phone.verify')) {
            if ($request->expectsJson()) {
                return response()->json(['message' => 'Vérification du téléphone requise.'], 403);
            }

            return redirect()->route('phone.verify');
        }

        return $next($request);
    }
}

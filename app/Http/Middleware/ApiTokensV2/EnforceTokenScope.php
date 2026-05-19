<?php

namespace App\Http\Middleware\ApiTokensV2;

use App\Models\Sanctum\PersonalAccessTokenV2;
use App\Services\ApiTokensV2\ScopeRegistry;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Usage : ->middleware('api_scope:read:bookings,write:bookings')
 * Le caller doit posséder AU MOINS UN scope listé (OR logic).
 */
class EnforceTokenScope
{
    public function handle(Request $request, Closure $next, string ...$requiredAny): Response
    {
        $user = $request->user();
        if (! $user) {
            return response()->json(['ok' => false, 'error' => 'unauthenticated'], 401);
        }
        // Si l'auth provient d'une session web (pas d'access token Sanctum), bypass scope check.
        $tokenRaw = method_exists($user, 'currentAccessToken') ? $user->currentAccessToken() : null;
        if (! $tokenRaw instanceof PersonalAccessTokenV2) {
            return $next($request);
        }
        $token = $tokenRaw;
        if (! $token->isUsable()) {
            $reason = $token->isSuspended()
                ? 'token_suspended'
                : ($token->isExpired() ? 'token_expired' : 'token_rotation_expired');
            return response()->json(['ok' => false, 'error' => $reason], 403);
        }

        $required = array_values(array_filter($requiredAny));
        if (empty($required)) {
            return $next($request);
        }

        $abilities = (array) ($token->abilities ?: []);
        // Sanctum '*' bypasses tout
        if (in_array('*', $abilities, true)) {
            return $next($request);
        }
        $registry = app(ScopeRegistry::class);
        foreach ($required as $r) {
            if ($registry->tokenHasScope($abilities, $r)) {
                return $next($request);
            }
        }
        return response()->json([
            'ok' => false,
            'error' => 'missing_scope',
            'required_any' => $required,
            'granted' => $abilities,
        ], 403);
    }
}

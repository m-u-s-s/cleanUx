<?php

namespace App\Http\Middleware;

use App\Models\Sanctum\PersonalAccessTokenV2;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/** EnforceTokenGrace Rejects requests made with a rotated token whose grace window has expired. */
class EnforceTokenGrace
{
    public function handle(Request $request, Closure $next): Response
    {
        $token = $request->user()?->currentAccessToken();

        if ($token === null) {
            return $next($request);
        }

        // Fast path: in-memory token says no grace ever set — skip DB round-trip.
        if ($token->rotation_grace_until === null) {
            return $next($request);
        }

        // Slow path: re-fetch from DB to bypass Sanctum's in-memory token cache.
        $fresh = PersonalAccessTokenV2::find($token->id);

        if (
            $fresh
            && $fresh->rotation_grace_until !== null
            && $fresh->rotation_grace_until->isPast()
        ) {
            return response()->json([
                'ok' => false,
                'error_code' => 'token_grace_expired',
                'message' => 'Token has been rotated and is no longer valid.',
            ], 401);
        }

        return $next($request);
    }
}

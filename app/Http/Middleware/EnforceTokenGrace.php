<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

use App\Models\Sanctum\PersonalAccessTokenV2;

/**
 * EnforceTokenGrace
 *
 * Rejects requests made with a rotated token whose grace window has expired.
 * Tokens in their grace window (rotation_grace_until in the future) are still
 * allowed to pass — only tokens where grace_until is set AND in the past are
 * rejected with a specific error code so mobile clients can re-authenticate.
 *
 * Note: we re-fetch the token from the DB because Sanctum's guard caches the
 * token instance in memory at authentication time (before any DB updates made
 * by concurrent requests would be visible on the in-memory object).
 */
class EnforceTokenGrace
{
    public function handle(Request $request, Closure $next): Response
    {
        $token = $request->user()?->currentAccessToken();

        if ($token && $token->id) {
            // Reload from DB to catch any grace-period updates made after
            // Sanctum resolved the token (e.g. rotation by a parallel request)
            $fresh = PersonalAccessTokenV2::find($token->id);

            if (
                $fresh
                && $fresh->rotation_grace_until !== null
                && $fresh->rotation_grace_until->isPast()
            ) {
                return response()->json([
                    'ok'         => false,
                    'error_code' => 'token_grace_expired',
                    'message'    => 'Token has been rotated and is no longer valid.',
                ], 401);
            }
        }

        return $next($request);
    }
}

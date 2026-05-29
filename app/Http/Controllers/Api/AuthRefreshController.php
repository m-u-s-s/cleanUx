<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

/**
 * @group Authentication
 *
 * @authenticated
 *
 * POST /api/auth/refresh
 *
 * Issues a new Sanctum token for the authenticated user while keeping the
 * old token valid for a short grace window (GRACE_MINUTES).  This prevents
 * the user from being logged out when a network glitch hits during rotation
 * (e.g. parallel refresh calls from mobile).
 *
 * Rotation chain:
 *   old_token.rotation_grace_until = now() + GRACE_MINUTES
 *   new_token.rotated_from_token_id = old_token.id
 *
 * Both writes (and the new token creation) are wrapped in a DB transaction so
 * a mid-flight process death cannot leave the old token permanently in grace
 * while the new token has no audit link (or vice-versa).
 */
class AuthRefreshController extends Controller
{
    private const GRACE_MINUTES = 5;

    public function __invoke(Request $request): JsonResponse
    {
        $user = $request->user();
        $oldToken = $user->currentAccessToken();

        // Mirror the ApiAuthController fallback chain: use the existing token
        // name when set; fall back to a per-token unique string so that tokens
        // with an empty name do not all collapse to the same device name on
        // refresh (which would break per-device revocation).
        $deviceName = $oldToken->name ?: ('device-'.$oldToken->id);

        $result = DB::transaction(function () use ($user, $oldToken, $deviceName) {
            $newToken = $user->createToken($deviceName);

            // Mark old token: still usable for GRACE_MINUTES after rotation
            $oldToken->forceFill([
                'rotation_grace_until' => now()->addMinutes(self::GRACE_MINUTES),
            ])->save();

            // Link new token back to its predecessor for audit
            $newToken->accessToken->forceFill([
                'rotated_from_token_id' => $oldToken->id,
            ])->save();

            return $newToken;
        });

        return response()->json([
            'token' => $result->plainTextToken,
            'expires_at' => $result->accessToken->expires_at?->toIso8601String(),
        ]);
    }
}

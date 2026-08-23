<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Support\Mobile\AppAudience;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

/**
 * POST /api/auth/refresh Issues a new Sanctum token for the authenticated user while keeping the old token valid for a short grace window (GRACE_MINUTES).
 *
 * @group Authentication
 *
 * @authenticated
 * old_token.rotation_grace_until = now() + GRACE_MINUTES
 * new_token.rotated_from_token_id = old_token.id
 */
class AuthRefreshController extends Controller
{
    private const GRACE_MINUTES = 5;

    public function __invoke(Request $request): JsonResponse
    {
        $user = $request->user();

        // Le renouvellement est une PORTE, au même titre que la connexion et la reprise de session.
        $app = AppAudience::declared($request);

        if (! AppAudience::allows($user, $app)) {
            return response()->json(AppAudience::refusal($user, (string) $app) + ['ok' => false], 403);
        }

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

<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
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
 */
class AuthRefreshController extends Controller
{
    private const GRACE_MINUTES = 5;

    public function __invoke(Request $request): JsonResponse
    {
        $user = $request->user();
        $oldToken = $user->currentAccessToken();
        $deviceName = $oldToken->name ?: 'mobile';

        $newToken = $user->createToken($deviceName);

        // Mark old token: still usable for GRACE_MINUTES after rotation
        $oldToken->forceFill([
            'rotation_grace_until' => now()->addMinutes(self::GRACE_MINUTES),
        ])->save();

        // Link new token back to its predecessor for audit
        $newToken->accessToken->forceFill([
            'rotated_from_token_id' => $oldToken->id,
        ])->save();

        return response()->json([
            'token'      => $newToken->plainTextToken,
            'expires_at' => $newToken->accessToken->expires_at?->toIso8601String(),
        ]);
    }
}

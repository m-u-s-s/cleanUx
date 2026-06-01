<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * @group Authentication
 *
 * @authenticated
 *
 * GET /api/auth/me
 *
 * Returns the currently authenticated user. Extracted from a closure so
 * that this route is compatible with `php artisan route:cache`.
 */
class AuthMeController extends Controller
{
    public function __invoke(Request $request): JsonResponse
    {
        $user = $request->user();

        // SP2 — expose le flag premium pour piloter la sélection prestataire côté
        // mobile (parité web). Sérialisé par-dessus l'utilisateur sans le muter.
        $payload = $user->toArray();
        $payload['is_premium'] = $user->customerProfile?->isPremium() ?? false;

        return response()->json($payload);
    }
}

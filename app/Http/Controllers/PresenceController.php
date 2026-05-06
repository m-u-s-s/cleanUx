<?php

namespace App\Http\Controllers;

use App\Services\Presence\PresenceTracker;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Phase 3 — Endpoints de présence appelés depuis echo-listeners.js.
 *
 * Routes (à ajouter dans routes/web.php ou routes/authenticated.php) :
 *   Route::middleware('auth')->group(function () {
 *       Route::post('/presence/touch', [PresenceController::class, 'touch']);
 *       Route::post('/presence/status', [PresenceController::class, 'setStatus']);
 *       Route::get('/presence/me', [PresenceController::class, 'me']);
 *   });
 */
class PresenceController extends Controller
{
    public function touch(Request $request): JsonResponse
    {
        PresenceTracker::touch($request->user());

        return response()->json([
            'ok'           => true,
            'last_seen_at' => now()->toIso8601String(),
        ]);
    }

    public function setStatus(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'status'         => ['required', 'string', 'in:available,busy,away,dnd,offline'],
            'custom_message' => ['nullable', 'string', 'max:140'],
        ]);

        PresenceTracker::setStatus(
            $request->user(),
            $validated['status'],
            $validated['custom_message'] ?? null,
        );

        return response()->json([
            'ok'             => true,
            'status'         => $validated['status'],
            'custom_message' => $validated['custom_message'] ?? null,
        ]);
    }

    public function me(Request $request): JsonResponse
    {
        return response()->json([
            'presence' => PresenceTracker::get($request->user()),
        ]);
    }
}

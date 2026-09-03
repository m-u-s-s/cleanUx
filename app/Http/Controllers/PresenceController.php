<?php

namespace App\Http\Controllers;

use App\Livewire\Provider\MaPresence;
use App\Services\Presence\PresenceTracker;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Phase 3 — Endpoints de presence de la MESSAGERIE, appeles depuis echo-listeners.js.
 *
 * Ne pas confondre avec `provider_presence`, la presence qui decide des missions : c'est
 * {@see MaPresence} qui la porte.
 */
class PresenceController extends Controller
{
    public function touch(Request $request): JsonResponse
    {
        PresenceTracker::touch($request->user());

        return response()->json([
            'ok' => true,
            'last_seen_at' => now()->toIso8601String(),
        ]);
    }

    public function setStatus(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'status' => ['required', 'string', 'in:available,busy,away,dnd,offline'],
            'custom_message' => ['nullable', 'string', 'max:140'],
        ]);

        PresenceTracker::setStatus(
            $request->user(),
            $validated['status'],
            $validated['custom_message'] ?? null,
        );

        return response()->json([
            'ok' => true,
            'status' => $validated['status'],
            'custom_message' => $validated['custom_message'] ?? null,
        ]);
    }
}

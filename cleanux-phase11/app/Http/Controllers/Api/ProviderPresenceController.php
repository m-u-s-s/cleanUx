<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Services\Provider\ProviderPresenceService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

/**
 * Phase 11 — Endpoints API mobile pour la presence prestataire.
 *
 * Routes (à déclarer dans routes/api.php) :
 *   POST /api/provider/presence/online      go online + position initiale
 *   POST /api/provider/presence/offline     go offline volontaire
 *   POST /api/provider/presence/heartbeat   ping périodique (toutes les 30s)
 *   GET  /api/provider/presence/me          mon état actuel
 *
 * Toutes ces routes sont sous middleware auth:sanctum + abort si pas prestataire.
 */
class ProviderPresenceController extends Controller
{
    public function __construct(
        protected ProviderPresenceService $presence,
    ) {}

    public function online(Request $request): JsonResponse
    {
        $user = $request->user();
        $this->abortIfNotProvider($user);

        $data = $request->validate([
            'lat'              => ['required', 'numeric', 'between:-90,90'],
            'lng'              => ['required', 'numeric', 'between:-180,180'],
            'accuracy_meters'  => ['nullable', 'numeric'],
            'battery_level'    => ['nullable', 'integer', 'min:0', 'max:100'],
            'app_version'      => ['nullable', 'string', 'max:30'],
        ]);

        $meta = collect($data)->only(['accuracy_meters', 'battery_level', 'app_version'])->filter()->all();

        try {
            $profile = $this->presence->goOnline(
                $user,
                (float) $data['lat'],
                (float) $data['lng'],
                $meta,
            );
        } catch (\DomainException $e) {
            return response()->json(['ok' => false, 'error' => $e->getMessage()], 403);
        }

        return response()->json([
            'ok'              => true,
            'is_online'       => true,
            'went_online_at'  => $profile->went_online_at?->toIso8601String(),
        ]);
    }

    public function offline(Request $request): JsonResponse
    {
        $user = $request->user();
        $this->abortIfNotProvider($user);

        try {
            $this->presence->goOffline($user);
        } catch (\DomainException $e) {
            return response()->json(['ok' => false, 'error' => $e->getMessage()], 403);
        }

        return response()->json([
            'ok'        => true,
            'is_online' => false,
        ]);
    }

    public function heartbeat(Request $request): JsonResponse
    {
        $user = $request->user();
        $this->abortIfNotProvider($user);

        $data = $request->validate([
            'lat'             => ['required', 'numeric', 'between:-90,90'],
            'lng'             => ['required', 'numeric', 'between:-180,180'],
            'accuracy_meters' => ['nullable', 'numeric'],
            'battery_level'   => ['nullable', 'integer', 'min:0', 'max:100'],
            'speed_kmh'       => ['nullable', 'numeric'],
            'heading'         => ['nullable', 'numeric'],
            'app_state'       => ['nullable', Rule::in(['foreground', 'background'])],
        ]);

        $meta = collect($data)
            ->only(['accuracy_meters', 'battery_level', 'speed_kmh', 'heading', 'app_state'])
            ->filter(fn ($v) => $v !== null && $v !== '')
            ->all();

        try {
            $profile = $this->presence->heartbeat(
                $user,
                (float) $data['lat'],
                (float) $data['lng'],
                $meta,
            );
        } catch (\DomainException $e) {
            return response()->json(['ok' => false, 'error' => $e->getMessage()], 403);
        }

        if (! $profile) {
            // Heartbeat envoyé alors qu'offline → le client doit re-go-online
            return response()->json([
                'ok'        => false,
                'is_online' => false,
                'error'     => 'Not online. Call /presence/online first.',
            ], 409);
        }

        return response()->json([
            'ok'                  => true,
            'is_online'           => true,
            'last_heartbeat_at'   => $profile->last_heartbeat_at?->toIso8601String(),
        ]);
    }

    public function me(Request $request): JsonResponse
    {
        $user = $request->user();
        $this->abortIfNotProvider($user);

        $profile = $user->providerProfile;
        if (! $profile) {
            return response()->json(['ok' => false, 'error' => 'No provider profile'], 404);
        }

        return response()->json([
            'ok'                => true,
            'is_online'         => (bool) $profile->is_online,
            'went_online_at'    => $profile->went_online_at?->toIso8601String(),
            'last_heartbeat_at' => $profile->last_heartbeat_at?->toIso8601String(),
            'current_lat'       => $profile->current_lat,
            'current_lng'       => $profile->current_lng,
        ]);
    }

    protected function abortIfNotProvider($user): void
    {
        // ProviderProfile peut exister même si user->role n'est pas explicitement provider
        // (cas employé d'une org cliente). On vérifie l'existence du profile.
        abort_if(
            ! $user || ! $user->providerProfile,
            403,
            'Vous devez être prestataire pour utiliser ces endpoints.'
        );
    }
}

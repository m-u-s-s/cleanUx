<?php

namespace App\Http\Controllers\Api\Provider;

use App\Http\Controllers\Controller;
use App\Models\ProviderPresence;
use App\Services\Presence\ProviderPresenceService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;

class PresenceController extends Controller
{
    public function status(Request $request, ProviderPresenceService $service): JsonResponse
    {
        $presence = $service->presenceFor($request->user());

        return response()->json([
            'data' => $this->present($presence),
        ]);
    }

    public function goOnline(Request $request, ProviderPresenceService $service): JsonResponse
    {
        $data = $request->validate([
            'lat' => ['nullable', 'numeric', 'between:-90,90'],
            'lng' => ['nullable', 'numeric', 'between:-180,180'],
            'radius_km' => ['nullable', 'integer', 'min:1', 'max:200'],
            'device_info' => ['nullable', 'string', 'max:255'],
        ]);

        $presence = $service->goOnline(
            provider: $request->user(),
            lat: isset($data['lat']) ? (float) $data['lat'] : null,
            lng: isset($data['lng']) ? (float) $data['lng'] : null,
            radiusKm: $data['radius_km'] ?? null,
            deviceInfo: $data['device_info'] ?? null,
        );

        return response()->json(['data' => $this->present($presence)]);
    }

    public function heartbeat(Request $request, ProviderPresenceService $service): JsonResponse
    {
        $data = $request->validate([
            'lat' => ['nullable', 'numeric', 'between:-90,90'],
            'lng' => ['nullable', 'numeric', 'between:-180,180'],
        ]);

        try {
            $presence = $service->heartbeat(
                provider: $request->user(),
                lat: isset($data['lat']) ? (float) $data['lat'] : null,
                lng: isset($data['lng']) ? (float) $data['lng'] : null,
            );
            return response()->json(['data' => $this->present($presence)]);
        } catch (ValidationException $e) {
            return response()->json([
                'error' => 'validation_failed',
                'errors' => $e->errors(),
            ], 422);
        }
    }

    public function goBreak(Request $request, ProviderPresenceService $service): JsonResponse
    {
        $presence = $service->goBreak($request->user());
        return response()->json(['data' => $this->present($presence)]);
    }

    public function goOffline(Request $request, ProviderPresenceService $service): JsonResponse
    {
        $presence = $service->goOffline($request->user());
        return response()->json(['data' => $this->present($presence)]);
    }

    protected function present(ProviderPresence $p): array
    {
        return [
            'status' => $p->status,
            'current_lat' => $p->current_lat,
            'current_lng' => $p->current_lng,
            'available_radius_km' => $p->available_radius_km,
            'heartbeat_at' => $p->heartbeat_at,
            'last_online_at' => $p->last_online_at,
            'online_minutes_today' => (int) $p->online_minutes_today,
            'online_minutes_week' => (int) $p->online_minutes_week,
            'is_active' => in_array($p->status, [
                ProviderPresence::STATUS_ONLINE,
                ProviderPresence::STATUS_BUSY,
                ProviderPresence::STATUS_ON_BREAK,
            ], true),
        ];
    }
}

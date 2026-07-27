<?php

namespace App\Http\Controllers\Api\Provider;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\Provider\GoOnlineRequest;
use App\Models\ProviderPresence;
use App\Services\Presence\ProviderPresenceService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;

/**
 * @group Provider Presence
 *
 * @authenticated
 */
class PresenceController extends Controller
{
    /**
     * Get the authenticated provider's current presence status.
     *
     * @response 200 {"data": {"status": "online", "current_lat": 50.85, "current_lng": 4.35, "available_radius_km": 15, "heartbeat_at": "2026-06-15T08:55:00+00:00", "last_online_at": "2026-06-15T08:00:00+00:00", "online_minutes_today": 235, "online_minutes_week": 1840, "is_active": true}}
     */
    public function status(Request $request, ProviderPresenceService $service): JsonResponse
    {
        $presence = $service->presenceFor($request->user());

        return response()->json([
            'data' => $this->present($presence),
        ]);
    }

    /**
     * Mark the provider as online and optionally set current GPS position.
     *
     * @bodyParam lat numeric GPS latitude (-90 to 90). Example: 50.85
     * @bodyParam lng numeric GPS longitude (-180 to 180). Example: 4.35
     * @bodyParam radius_km integer Availability radius in kilometres (1-200). Example: 15
     * @bodyParam device_info string Optional device identifier string (max 255 chars). Example: iPhone 15 Pro
     *
     * @response 200 {"data": {"status": "online", "current_lat": 50.85, "current_lng": 4.35, "available_radius_km": 15, "heartbeat_at": "2026-06-15T09:00:00+00:00", "last_online_at": "2026-06-15T09:00:00+00:00", "online_minutes_today": 0, "online_minutes_week": 1840, "is_active": true}}
     */
    public function goOnline(GoOnlineRequest $request, ProviderPresenceService $service): JsonResponse
    {
        $data = $request->validated();

        $presence = $service->goOnline(
            provider: $request->user(),
            lat: isset($data['lat']) ? (float) $data['lat'] : null,
            lng: isset($data['lng']) ? (float) $data['lng'] : null,
            radiusKm: $data['radius_km'] ?? null,
            deviceInfo: $data['device_info'] ?? null,
        );

        return response()->json(['data' => $this->present($presence)]);
    }

    /**
     * Send a presence heartbeat to stay online (call every 60-120 seconds).
     *
     * Providers not sending a heartbeat within the stale threshold (default 5 min) are
     * automatically set offline by the presence:scan-stale cron job.
     *
     * @bodyParam lat numeric Updated GPS latitude (-90 to 90). Example: 50.851
     * @bodyParam lng numeric Updated GPS longitude (-180 to 180). Example: 4.351
     *
     * @response 200 {"data": {"status": "online", "current_lat": 50.851, "current_lng": 4.351, "available_radius_km": 15, "heartbeat_at": "2026-06-15T09:02:00+00:00", "last_online_at": "2026-06-15T09:00:00+00:00", "online_minutes_today": 2, "online_minutes_week": 1842, "is_active": true}}
     * @response 422 {"error": "validation_failed", "errors": {"lat": ["The lat field must be between -90 and 90."]}}
     */
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

    /**
     * Mark the provider as busy (manually — e.g. finishing a job off-platform).
     *
     * Note: `busy` is also set automatically by BookingObserver when a mission starts, and
     * reset to `online` when it completes; a manual call here is overridden by that flow.
     * Like every other transition, this is idempotent.
     *
     * @response 200 {"data": {"status": "busy", "current_lat": 50.85, "current_lng": 4.35, "available_radius_km": 15, "heartbeat_at": "2026-06-15T11:00:00+00:00", "last_online_at": "2026-06-15T09:00:00+00:00", "online_minutes_today": 120, "online_minutes_week": 1960, "is_active": true}}
     */
    public function goBusy(Request $request, ProviderPresenceService $service): JsonResponse
    {
        $presence = $service->goBusy($request->user());

        return response()->json(['data' => $this->present($presence)]);
    }

    /**
     * Mark the provider as on break (temporarily unavailable for new missions).
     *
     * @response 200 {"data": {"status": "on_break", "current_lat": 50.85, "current_lng": 4.35, "available_radius_km": 15, "heartbeat_at": "2026-06-15T12:00:00+00:00", "last_online_at": "2026-06-15T09:00:00+00:00", "online_minutes_today": 180, "online_minutes_week": 2020, "is_active": true}}
     */
    public function goBreak(Request $request, ProviderPresenceService $service): JsonResponse
    {
        $presence = $service->goBreak($request->user());

        return response()->json(['data' => $this->present($presence)]);
    }

    /**
     * Mark the provider as offline (end of shift).
     *
     * @response 200 {"data": {"status": "offline", "current_lat": null, "current_lng": null, "available_radius_km": null, "heartbeat_at": "2026-06-15T17:00:00+00:00", "last_online_at": "2026-06-15T09:00:00+00:00", "online_minutes_today": 460, "online_minutes_week": 2300, "is_active": false}}
     */
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

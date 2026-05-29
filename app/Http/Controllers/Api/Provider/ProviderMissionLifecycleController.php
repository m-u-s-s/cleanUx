<?php

namespace App\Http\Controllers\Api\Provider;

use App\Http\Controllers\Controller;
use App\Models\Mission;
use App\Services\Missions\MissionLifecycleService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Phase 12 — Lifecycle d'une mission côté prestataire mobile.
 *
 * GET    /api/provider/missions/active            → mes missions actives
 * GET    /api/provider/missions/{id}              → détail
 * POST   /api/provider/missions/{id}/start        → "je pars" (en_route)
 * POST   /api/provider/missions/{id}/arrive       → "je suis arrivé"
 * POST   /api/provider/missions/{id}/complete     → "j'ai terminé" (avec code de fin)
 *
 * Wraps autour du MissionLifecycleService existant (méthodes setEnRoute,
 * setArrived, completeMission). Garantit que le user a bien le droit d'agir
 * sur la mission (assignment ou lead).
 */
/**
 * @group Mission Lifecycle
 *
 * @authenticated
 */
class ProviderMissionLifecycleController extends Controller
{
    public function __construct(
        protected MissionLifecycleService $lifecycle,
    ) {}

    /**
     * List the provider's active missions (assigned, en_route, arrived, started, paused).
     *
     * @response 200 {"ok": true, "count": 1, "data": [{"id": 12, "status": "assigned", "planned_start_at": "2026-06-15T09:00:00+00:00", "actual_start_at": null, "actual_end_at": null, "estimated_duration_minutes": 120, "actual_duration_minutes": null, "booking": {"reference": "CUX-A1B2C3", "service_name": "Nettoyage domicile", "address": "Rue de la Loi 1", "city": "Bruxelles", "postal_code": "1000", "destination_lat": 50.846, "destination_lng": 4.352, "scheduled_date": "2026-06-15", "scheduled_time": "09:00:00"}}]}
     */
    public function active(Request $request): JsonResponse
    {
        $user = $request->user();

        $missions = Mission::query()
            ->where(function ($q) use ($user) {
                $q->where('lead_provider_user_id', $user->id)
                    ->orWhereHas('assignments', function ($q2) use ($user) {
                        $q2->where('user_id', $user->id)
                            ->where('assignment_status', 'accepted');
                    });
            })
            ->whereIn('status', ['assigned', 'en_route', 'arrived', 'started', 'paused'])
            ->with([
                'booking:id,booking_reference,address,city,postal_code,scheduled_date,scheduled_time,service_catalog_id,destination_lat,destination_lng,customer_comment',
                'booking.serviceCatalog:id,name',
            ])
            ->orderBy('planned_start_at')
            ->get();

        return response()->json([
            'ok' => true,
            'count' => $missions->count(),
            'data' => $missions->map(fn ($m) => $this->serialize($m))->all(),
        ]);
    }

    /**
     * Get full details for a single mission including booking, client info, and checklists.
     *
     * @response 200 {"ok": true, "data": {"id": 12, "status": "assigned", "planned_start_at": "2026-06-15T09:00:00+00:00", "actual_start_at": null, "actual_end_at": null, "estimated_duration_minutes": 120, "actual_duration_minutes": null, "booking": {"reference": "CUX-A1B2C3", "service_name": "Nettoyage domicile", "address": "Rue de la Loi 1", "city": "Bruxelles", "postal_code": "1000", "destination_lat": 50.846, "destination_lng": 4.352, "scheduled_date": "2026-06-15", "scheduled_time": "09:00:00", "customer_comment": "Apporter matériel"}, "client": {"id": 1, "name": "Alice Dupont", "phone": "+32471000001"}, "client_price": 75.0, "provider_cost": 55.0, "checklists_count": 1, "checklist_items_pending": 5}}
     * @response 403 {"message": "Vous n'êtes pas assigné à cette mission."}
     */
    public function show(Request $request, Mission $mission): JsonResponse
    {
        $this->authorizeProvider($request, $mission);

        $mission->load([
            'booking:id,booking_reference,address,city,postal_code,scheduled_date,scheduled_time,service_catalog_id,destination_lat,destination_lng,customer_comment,client_id,customer_user_id',
            'booking.serviceCatalog:id,name',
            'booking.client:id,name,phone',
            'booking.customer:id,name,phone',
            'assignments',
            'checklists.items',
        ]);

        return response()->json([
            'ok' => true,
            'data' => $this->serialize($mission, detailed: true),
        ]);
    }

    /**
     * Mark the mission as en_route (provider is departing toward the client).
     *
     * @bodyParam lat numeric Current GPS latitude of the provider (-90 to 90). Example: 50.843
     * @bodyParam lng numeric Current GPS longitude of the provider (-180 to 180). Example: 4.348
     *
     * @response 200 {"ok": true, "mission_id": 12, "status": "en_route"}
     * @response 403 {"message": "Vous n'êtes pas assigné à cette mission."}
     * @response 422 {"message": "Mission cannot transition from current status to en_route."}
     */
    public function start(Request $request, Mission $mission): JsonResponse
    {
        $this->authorizeProvider($request, $mission);

        $data = $request->validate([
            'lat' => ['nullable', 'numeric', 'between:-90,90'],
            'lng' => ['nullable', 'numeric', 'between:-180,180'],
        ]);

        // Lifecycle exceptions propagate to ApiJsonRenderer for unified JSON error shape.
        $mission = $this->lifecycle->setEnRoute($mission, $request->user());

        return response()->json([
            'ok' => true,
            'mission_id' => $mission->id,
            'status' => $mission->status,
        ]);
    }

    /**
     * Mark the mission as arrived (provider is at the client's location).
     *
     * @bodyParam lat numeric GPS latitude at arrival (-90 to 90). Example: 50.846
     * @bodyParam lng numeric GPS longitude at arrival (-180 to 180). Example: 4.352
     *
     * @response 200 {"ok": true, "mission_id": 12, "status": "arrived"}
     * @response 403 {"message": "Vous n'êtes pas assigné à cette mission."}
     * @response 422 {"message": "Mission cannot transition from current status to arrived."}
     */
    public function arrive(Request $request, Mission $mission): JsonResponse
    {
        $this->authorizeProvider($request, $mission);

        $data = $request->validate([
            'lat' => ['nullable', 'numeric', 'between:-90,90'],
            'lng' => ['nullable', 'numeric', 'between:-180,180'],
        ]);

        // Lifecycle exceptions propagate to ApiJsonRenderer for unified JSON error shape.
        $mission = $this->lifecycle->setArrived(
            $mission,
            $request->user(),
            isset($data['lat']) ? (float) $data['lat'] : null,
            isset($data['lng']) ? (float) $data['lng'] : null,
        );

        return response()->json([
            'ok' => true,
            'mission_id' => $mission->id,
            'status' => $mission->status,
        ]);
    }

    /**
     * Mark the mission as completed.
     *
     * If the booking has an end QR-code set up, `end_code` is required. Pass the 6-digit
     * code scanned from the client's QR at end-of-service.
     *
     * @bodyParam lat numeric GPS latitude at completion (-90 to 90). Example: 50.846
     * @bodyParam lng numeric GPS longitude at completion (-180 to 180). Example: 4.352
     * @bodyParam end_code string 6-digit end verification code from client QR (required when booking has end code). Example: 482951
     *
     * @response 200 {"ok": true, "mission_id": 12, "status": "completed", "duration_minutes": 118}
     * @response 403 {"message": "Vous n'êtes pas assigné à cette mission."}
     * @response 422 {"ok": false, "message": "Le code de fin est requis pour clôturer cette mission."}
     */
    public function complete(Request $request, Mission $mission): JsonResponse
    {
        $this->authorizeProvider($request, $mission);

        $data = $request->validate([
            'lat' => ['nullable', 'numeric', 'between:-90,90'],
            'lng' => ['nullable', 'numeric', 'between:-180,180'],
            'end_code' => ['nullable', 'string', 'size:6'],
        ]);

        $hasPendingEndCode = $mission->verificationCodes()
            ->where('code_type', 'end')
            ->where('is_consumed', false)
            ->exists();

        if ($hasPendingEndCode && empty($data['end_code'])) {
            return response()->json([
                'ok' => false,
                'message' => 'Le code de fin est requis pour clôturer cette mission.',
            ], 422);
        }

        $lat = isset($data['lat']) ? (float) $data['lat'] : null;
        $lng = isset($data['lng']) ? (float) $data['lng'] : null;

        if ($hasPendingEndCode && ! empty($data['end_code'])) {
            $mission = $this->lifecycle->validateEndCode($mission, $request->user(), $data['end_code'], $lat, $lng);
        } else {
            $mission = $this->lifecycle->completeMission($mission, $request->user(), $lat, $lng);
        }

        return response()->json([
            'ok' => true,
            'mission_id' => $mission->id,
            'status' => $mission->status,
            'duration_minutes' => $mission->actual_duration_minutes,
        ]);
    }

    // ──────────────────────────────────────────────────────
    // Helpers
    // ──────────────────────────────────────────────────────

    protected function authorizeProvider(Request $request, Mission $mission): void
    {
        $userId = $request->user()->id;

        $isLead = (int) $mission->lead_provider_user_id === (int) $userId;
        $isAssigned = $mission->assignments()
            ->where('user_id', $userId)
            ->whereIn('assignment_status', ['accepted', 'en_route', 'arrived'])
            ->exists();

        abort_if(
            ! $isLead && ! $isAssigned,
            403,
            "Vous n'êtes pas assigné à cette mission."
        );
    }

    protected function serialize(Mission $mission, bool $detailed = false): array
    {
        $booking = $mission->booking;

        $base = [
            'id' => $mission->id,
            'status' => $mission->status,
            'planned_start_at' => $mission->planned_start_at?->toIso8601String(),
            'actual_start_at' => $mission->actual_start_at?->toIso8601String(),
            'actual_end_at' => $mission->actual_end_at?->toIso8601String(),
            'estimated_duration_minutes' => $mission->estimated_duration_minutes,
            'actual_duration_minutes' => $mission->actual_duration_minutes,
            'booking' => $booking ? [
                'reference' => $booking->booking_reference,
                'service_name' => $booking->serviceCatalog?->name,
                'address' => $booking->address,
                'city' => $booking->city,
                'postal_code' => $booking->postal_code,
                'destination_lat' => $booking->destination_lat,
                'destination_lng' => $booking->destination_lng,
                'scheduled_date' => $booking->scheduled_date,
                'scheduled_time' => $booking->scheduled_time,
            ] : null,
        ];

        if ($detailed && $booking) {
            $base['booking']['customer_comment'] = $booking->customer_comment ?? null;
            $client = $booking->client ?? $booking->customer ?? null;
            $base['client'] = $client ? [
                'id' => $client->id,
                'name' => $client->name,
                'phone' => $client->phone ?? null,
            ] : null;
            $base['client_price'] = $mission->client_price;
            $base['provider_cost'] = $mission->provider_cost;
            $base['checklists_count'] = $mission->checklists->count();
            $base['checklist_items_pending'] = $mission->checklists
                ->flatMap(fn ($c) => $c->items)
                ->where('status', '!=', 'done')
                ->count();
        }

        return $base;
    }
}

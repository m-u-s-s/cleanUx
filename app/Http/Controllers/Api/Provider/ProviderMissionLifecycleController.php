<?php

namespace App\Http\Controllers\Api\Provider;

use App\Http\Controllers\Api\Concerns\FormatsBookingSchedule;
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
    use FormatsBookingSchedule;

    /**
     * Colonnes de réservation nécessaires au payload plat. Chargées sur LES DEUX chaînes de
     * résolution (bookingViaBookingId et rendezVous) — voir serialize().
     */
    private const BOOKING_COLUMNS = 'id,booking_reference,address,city,postal_code,scheduled_date,scheduled_time,service_catalog_id,destination_lat,destination_lng,customer_comment,client_id,customer_user_id';

    public function __construct(
        protected MissionLifecycleService $lifecycle,
    ) {}

    /**
     * @return array<int, string>
     */
    private function bookingEagerLoads(): array
    {
        $loads = [];

        foreach (['bookingViaBookingId', 'rendezVous'] as $relation) {
            $loads[] = $relation.':'.self::BOOKING_COLUMNS;
            $loads[] = $relation.'.serviceCatalog:id,name';
            $loads[] = $relation.'.client:id,name,phone';
            $loads[] = $relation.'.customer:id,name,phone';
        }

        return $loads;
    }

    /**
     * List the provider's active missions (assigned, en_route, arrived, started, paused).
     *
     * @response 200 {"ok": true, "count": 1, "data": [{"id": 12, "status": "assigned", "service_name": "Nettoyage domicile", "client_name": "Alice Dupont", "address": "Rue de la Loi 1", "city": "Bruxelles", "postal_code": "1000", "latitude": 50.846, "longitude": 4.352, "scheduled_date": "2026-06-15", "scheduled_time": "09:00", "booking_id": 4, "booking_reference": "CUX-A1B2C3", "planned_start_at": "2026-06-15T09:00:00+00:00", "actual_start_at": null, "actual_end_at": null, "estimated_duration_minutes": 120, "actual_duration_minutes": null}]}
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
            ->with($this->bookingEagerLoads())
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
     * @response 200 {"ok": true, "data": {"id": 12, "status": "assigned", "service_name": "Nettoyage domicile", "client_name": "Alice Dupont", "address": "Rue de la Loi 1", "city": "Bruxelles", "postal_code": "1000", "latitude": 50.846, "longitude": 4.352, "scheduled_date": "2026-06-15", "scheduled_time": "09:00", "booking_id": 4, "booking_reference": "CUX-A1B2C3", "planned_start_at": "2026-06-15T09:00:00+00:00", "actual_start_at": null, "actual_end_at": null, "estimated_duration_minutes": 120, "actual_duration_minutes": null, "client_phone": "+32471000001", "notes": "Apporter matériel", "total_price": 75.0, "provider_cost": 55.0, "checklists_count": 1, "checklist_items_pending": 5}}
     * @response 403 {"message": "Vous n'êtes pas assigné à cette mission."}
     */
    public function show(Request $request, Mission $mission): JsonResponse
    {
        $this->authorizeProvider($request, $mission);

        $mission->load(array_merge($this->bookingEagerLoads(), [
            'assignments',
            'checklists.items',
        ]));

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
    /**
     * Close the mission with the code the client shows on their screen.
     *
     * Mirror of the presence scan, at the other end of the visit. The end code was sent to the
     * client by SMS and typed back by the provider; an SMS travels, a code read off the client's
     * screen requires both people in the same room. Closing captures the pre-authorised payment,
     * so the client's assent must be a deliberate gesture rather than a forwarded message.
     *
     * @bodyParam code string required The 6-digit code read from the client's QR. Example: 731204
     *
     * @response 200 {"ok": true, "mission_id": 12, "status": "completed"}
     * @response 403 {"message": "Vous n'êtes pas assigné à cette mission."}
     * @response 422 {"ok": false, "message": "Code invalide."}
     */
    public function completeByQr(Request $request, Mission $mission): JsonResponse
    {
        $this->authorizeProvider($request, $mission);

        $data = $request->validate([
            'code' => ['required', 'string', 'max:191'],
            'lat' => ['nullable', 'numeric', 'between:-90,90'],
            'lng' => ['nullable', 'numeric', 'between:-180,180'],
            'accuracy_m' => ['nullable', 'numeric', 'min:0'],
            'mocked' => ['nullable', 'boolean'],
        ]);

        try {
            // `validateEndCode` consomme le code PUIS clôture — encaissement compris. Un code
            // refusé ne doit donc rien déclencher, d'où la validation en amont de la clôture.
            //
            // C'est le SEUL chemin de clôture où la position est exigée, et c'est voulu : ici
            // l'application en dispose toujours, et c'est ici qu'un code photographié ou dicté
            // servirait à clôturer à distance. Les chemins web n'ont pas de position à offrir.
            $mission = $this->lifecycle->validateEndCode(
                $mission,
                $request->user(),
                $data['code'],
                isset($data['lat']) ? (float) $data['lat'] : null,
                isset($data['lng']) ? (float) $data['lng'] : null,
                isset($data['accuracy_m']) ? (float) $data['accuracy_m'] : null,
                (bool) ($data['mocked'] ?? false),
                requirePosition: (bool) config('trip_tracking.presence_require_position', true),
            );
        } catch (\RuntimeException $e) {
            return response()->json(['ok' => false, 'message' => $e->getMessage()], 422);
        }

        return response()->json([
            'ok' => true,
            'mission_id' => $mission->id,
            'status' => $mission->status,
        ]);
    }

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
    /**
     * Démarre une mission sur laquelle le prestataire est arrivé (arrived → started).
     *
     * Ce passage n'existait que sur des routes WEB à session (routes/missions.php), inaccessibles
     * à une app authentifiée par jeton : un prestataire arrivé sur place ne pouvait pas démarrer
     * sa mission depuis son téléphone. `start` ci-dessus ne fait PAS cela — il met en route
     * (setEnRoute), et l'appeler depuis `arrived` produit un 422, la transition étant invalide.
     *
     * Le code de début est envoyé au client par SMS à l'arrivée (setArrived) : c'est lui qui
     * atteste sa présence. Même contrat que `complete` et son end_code.
     *
     * @bodyParam start_code string Code à six chiffres communiqué par le client. Example: 482915
     *
     * @response 200 {"ok": true, "mission_id": 12, "status": "started"}
     * @response 403 {"message": "Vous n'êtes pas assigné à cette mission."}
     * @response 422 {"ok": false, "message": "Le code de début est requis pour démarrer cette mission."}
     */
    public function begin(Request $request, Mission $mission): JsonResponse
    {
        $this->authorizeProvider($request, $mission);

        $data = $request->validate([
            'lat' => ['nullable', 'numeric', 'between:-90,90'],
            'lng' => ['nullable', 'numeric', 'between:-180,180'],
            'start_code' => ['nullable', 'string', 'size:6'],
        ]);

        $hasPendingStartCode = $mission->verificationCodes()
            ->where('code_type', 'start')
            ->where('is_consumed', false)
            ->exists();

        if ($hasPendingStartCode && empty($data['start_code'])) {
            return response()->json([
                'ok' => false,
                'message' => 'Le code de début est requis pour démarrer cette mission.',
            ], 422);
        }

        $lat = isset($data['lat']) ? (float) $data['lat'] : null;
        $lng = isset($data['lng']) ? (float) $data['lng'] : null;

        // Sans code en attente, la mission n'a pas été marquée arrivée par le flux normal : on
        // refuse plutôt que d'inventer un démarrage sans attestation de présence.
        if (! $hasPendingStartCode) {
            return response()->json([
                'ok' => false,
                'message' => "Aucun code de début en attente : marquez d'abord votre arrivée.",
            ], 422);
        }

        $mission = $this->lifecycle->validateStartCode(
            $mission,
            $request->user(),
            (string) $data['start_code'],
            $lat,
            $lng,
        );

        return response()->json([
            'ok' => true,
            'mission_id' => $mission->id,
            'status' => $mission->status,
        ]);
    }

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

    /**
     * Payload PLAT, aligné sur le type TS `Mission` (mobile/provider/src/missions/types.ts) que
     * consomment MissionDetailScreen, TrackingScreen et MissionsListScreen. Même traitement que
     * ProviderMissionAssignmentController::serializeForList() pour l'inbox : la structure
     * imbriquée { booking: {...}, client: {...} } rendait chacune de ces lectures `undefined`.
     */
    protected function serialize(Mission $mission, bool $detailed = false): array
    {
        // Résolution identique à celle de l'inbox : booking() choisit sa FK depuis
        // $this->booking_id, ce que Laravel ne peut pas faire en eager load (il résout la
        // relation sur une instance vierge, où l'attribut est toujours vide, et retombe donc
        // toujours sur rendez_vous_id). Les deux colonnes portent un bookings.id selon le
        // chemin de création, d'où les deux chaînes chargées puis départagées ici.
        $booking = $mission->bookingViaBookingId ?? $mission->rendezVous;
        $client = $booking?->client ?? $booking?->customer;

        $base = [
            'id' => $mission->id,
            'status' => $mission->status,
            'service_name' => $booking?->serviceCatalog?->name,
            'client_name' => $client?->name,
            'address' => $booking?->address,
            'city' => $booking?->city,
            'postal_code' => $booking?->postal_code,
            // Destination de la mission, c'est-à-dire l'endroit où le prestataire doit se rendre :
            // c'est ce dont TrackingScreen a besoin pour la distance, l'ETA et le géofence
            // d'arrivée à 150 m. Surtout PAS start_lat/end_lat, qui portent la position GPS du
            // PRESTATAIRE au départ et à la clôture (MissionLifecycleService) : les utiliser
            // ferait converger la distance vers zéro dès le départ.
            'latitude' => $this->toFloat($mission->destination_lat ?? $booking?->destination_lat),
            'longitude' => $this->toFloat($mission->destination_lng ?? $booking?->destination_lng),
            'scheduled_date' => $this->formatScheduledDate($booking?->scheduled_date),
            'scheduled_time' => $this->formatScheduledTime($booking?->scheduled_time),
            'booking_id' => $booking?->id,
            'booking_reference' => $booking?->booking_reference,
            'planned_start_at' => $mission->planned_start_at?->toIso8601String(),
            'actual_start_at' => $mission->actual_start_at?->toIso8601String(),
            'actual_end_at' => $mission->actual_end_at?->toIso8601String(),
            'estimated_duration_minutes' => $mission->estimated_duration_minutes,
            'actual_duration_minutes' => $mission->actual_duration_minutes,
        ];

        if ($detailed) {
            $base['client_phone'] = $client?->phone;
            $base['notes'] = $booking?->customer_comment;
            $base['total_price'] = $this->toFloat($mission->client_price);
            $base['provider_cost'] = $this->toFloat($mission->provider_cost);
            $base['checklists_count'] = $mission->checklists->count();
            $base['checklist_items_pending'] = $mission->checklists
                ->flatMap(fn ($c) => $c->items)
                ->where('status', '!=', 'done')
                ->count();
        }

        return $base;
    }

    /** Les colonnes décimales reviennent en chaîne (cast decimal:7) : le mobile attend un nombre. */
    private function toFloat(mixed $value): ?float
    {
        return $value === null ? null : (float) $value;
    }
}

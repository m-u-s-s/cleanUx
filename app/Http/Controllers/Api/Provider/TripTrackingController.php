<?php

namespace App\Http\Controllers\Api\Provider;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\Provider\PingTripRequest;
use App\Models\Booking;
use App\Models\Mission;
use App\Models\TripTrackingSession;
use App\Services\Missions\MissionLifecycleService;
use App\Services\TripTracking\PresenceCodeService;
use App\Services\TripTracking\TripTrackingService;
use App\Support\Domain\MissionStatus;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\ValidationException;

/**
 * @group Trip Tracking
 *
 * @authenticated
 */
class TripTrackingController extends Controller
{
    /**
     * Start a trip tracking session for a booking.
     *
     * Creates a new session in `enroute` status. Call once when the provider departs.
     * The session is used to record GPS pings and share real-time ETA with the client.
     *
     * @bodyParam start_lat numeric Starting GPS latitude (-90 to 90). Example: 50.843
     * @bodyParam start_lng numeric Starting GPS longitude (-180 to 180). Example: 4.348
     *
     * @response 201 {"data": {"id": 5, "code": "TRK-ABC123", "booking_id": 42, "status": "enroute", "destination": {"lat": 50.846, "lng": 4.352}, "last_position": {"lat": 50.843, "lng": 4.348, "speed_mps": null, "ping_at": null}, "eta_seconds": null, "total_distance_m": 0, "points_count": 0, "started_at": "2026-06-15T08:30:00+00:00", "arrived_at": null, "in_mission_at": null, "ended_at": null}}
     * @response 403 {"message": "Not assigned to this booking."}
     */
    public function start(Request $request, Booking $booking, TripTrackingService $service): JsonResponse
    {
        $this->authorizeProvider($request, $booking);

        $data = $request->validate([
            'start_lat' => ['nullable', 'numeric', 'between:-90,90'],
            'start_lng' => ['nullable', 'numeric', 'between:-180,180'],
        ]);

        $session = $service->startSession(
            provider: $request->user(),
            booking: $booking,
            startLat: isset($data['start_lat']) ? (float) $data['start_lat'] : null,
            startLng: isset($data['start_lng']) ? (float) $data['start_lng'] : null,
        );

        return response()->json([
            'data' => $this->presentSession($session),
        ], 201);
    }

    /**
     * Record a GPS ping for an active tracking session.
     *
     * Call every 5-15 seconds while en route. Pings with duplicate `sequence` numbers are
     * deduplicated. Auto-transitions session to `arrived` when within 150 m of destination.
     *
     * @bodyParam lat numeric required Current GPS latitude (-90 to 90). Example: 50.845
     * @bodyParam lng numeric required Current GPS longitude (-180 to 180). Example: 4.351
     * @bodyParam accuracy_m number GPS accuracy in metres. Example: 8.5
     * @bodyParam speed_mps number Current speed in metres per second. Example: 5.2
     * @bodyParam heading_deg number Compass heading in degrees (0-360). Example: 45.0
     * @bodyParam sequence integer Client-side monotonic sequence number for deduplication. Example: 42
     *
     * @response 201 {"data": {"point_id": 87, "distance_to_dest_m": 520, "eta_seconds": 180, "session_status": "enroute"}}
     * @response 422 {"error": "validation_failed", "errors": {"lat": ["The lat field is required."]}}
     */
    public function ping(PingTripRequest $request, TripTrackingSession $session, TripTrackingService $service): JsonResponse
    {
        $this->authorizeProviderForSession($request, $session);

        $data = $request->validated();

        try {
            $point = $service->recordPing(
                session: $session,
                lat: (float) $data['lat'],
                lng: (float) $data['lng'],
                accuracyM: isset($data['accuracy_m']) ? (float) $data['accuracy_m'] : null,
                speedMps: isset($data['speed_mps']) ? (float) $data['speed_mps'] : null,
                headingDeg: isset($data['heading_deg']) ? (float) $data['heading_deg'] : null,
                clientSequence: $data['sequence'] ?? null,
            );

            return response()->json([
                'data' => [
                    'point_id' => $point->id,
                    'distance_to_dest_m' => $point->distance_to_dest_m,
                    'eta_seconds' => $point->eta_seconds,
                    'session_status' => $session->fresh()->status,
                ],
            ], 201);
        } catch (ValidationException $e) {
            return response()->json([
                'error' => 'validation_failed',
                'errors' => $e->errors(),
            ], 422);
        }
    }

    /**
     * Transition the tracking session to in_mission (work has started on site).
     *
     * @response 200 {"data": {"id": 5, "code": "TRK-ABC123", "booking_id": 42, "status": "in_mission", "destination": {"lat": 50.846, "lng": 4.352}, "last_position": {"lat": 50.846, "lng": 4.352, "speed_mps": 0.0, "ping_at": "2026-06-15T09:01:00+00:00"}, "eta_seconds": 0, "total_distance_m": 1250, "points_count": 35, "started_at": "2026-06-15T08:30:00+00:00", "arrived_at": "2026-06-15T08:58:00+00:00", "in_mission_at": "2026-06-15T09:01:00+00:00", "ended_at": null}}
     * @response 403 {"message": "Not your session."}
     * @response 422 {"error": "validation_failed", "errors": {"status": ["Session is not in arrived state."]}}
     */
    public function markInMission(Request $request, TripTrackingSession $session, TripTrackingService $service): JsonResponse
    {
        $this->authorizeProviderForSession($request, $session);

        try {
            $updated = $service->markInMission($session);

            return response()->json(['data' => $this->presentSession($updated)]);
        } catch (ValidationException $e) {
            return response()->json([
                'error' => 'validation_failed',
                'errors' => $e->errors(),
            ], 422);
        }
    }

    public function pause(Request $request, TripTrackingSession $session, TripTrackingService $service): JsonResponse
    {
        $this->authorizeProviderForSession($request, $session);

        try {
            return response()->json(['data' => $this->presentSession($service->pauseSession($session))]);
        } catch (ValidationException $e) {
            return response()->json(['error' => 'validation_failed', 'errors' => $e->errors()], 422);
        }
    }

    public function resume(Request $request, TripTrackingSession $session, TripTrackingService $service): JsonResponse
    {
        $this->authorizeProviderForSession($request, $session);

        return response()->json(['data' => $this->presentSession($service->resumeSession($session))]);
    }

    /**
     * End a trip tracking session.
     *
     * @bodyParam reason string Optional reason for ending the session (max 255 chars). Example: Mission terminée
     *
     * @response 200 {"data": {"id": 5, "code": "TRK-ABC123", "booking_id": 42, "status": "ended", "destination": {"lat": 50.846, "lng": 4.352}, "last_position": {"lat": 50.846, "lng": 4.352, "speed_mps": 0.0, "ping_at": "2026-06-15T11:00:00+00:00"}, "eta_seconds": 0, "total_distance_m": 1250, "points_count": 210, "started_at": "2026-06-15T08:30:00+00:00", "arrived_at": "2026-06-15T08:58:00+00:00", "in_mission_at": "2026-06-15T09:01:00+00:00", "ended_at": "2026-06-15T11:02:00+00:00"}}
     * @response 403 {"message": "Not your session."}
     */
    public function end(Request $request, TripTrackingSession $session, TripTrackingService $service): JsonResponse
    {
        $this->authorizeProviderForSession($request, $session);
        $params = $request->validate([
            'reason' => ['nullable', 'string', 'max:255'],
        ]);
        $updated = $service->endSession($session, $params['reason'] ?? null);

        return response()->json(['data' => $this->presentSession($updated)]);
    }

    /**
     * Confirm the provider's physical presence with the code shown by the client.
     *
     * The geofence proves proximity, not presence — a phone 100 m from the door crosses it. The
     * client displays a single-use code that the provider scans on site, which requires both
     * devices in the same place.
     *
     * @bodyParam code string required The 6-digit code read from the client's QR. Example: 482951
     *
     * @response 200 {"data": {"id": 5, "status": "in_mission", "presence_confirmed_at": "2026-07-30T18:40:00+00:00"}}
     * @response 403 {"message": "Not your session."}
     * @response 422 {"error": "validation_failed", "errors": {"code": ["Code invalide."]}}
     */
    public function confirmPresence(Request $request, TripTrackingSession $session, PresenceCodeService $codes): JsonResponse
    {
        $this->authorizeProviderForSession($request, $session);

        $data = $request->validate([
            'code' => ['required', 'string', 'max:191'],
        ]);

        try {
            $updated = $codes->confirm($session, $data['code'], $request->user());

            return response()->json([
                'data' => $this->presentSession($updated),
                'mission_started' => $this->startMissionOnPresence($updated, $request->user()),
            ]);
        } catch (ValidationException $e) {
            return response()->json([
                'error' => 'validation_failed',
                'errors' => $e->errors(),
            ], 422);
        }
    }

    /**
     * Démarre la mission dès la présence prouvée.
     *
     * Le démarrage exigeait jusqu'ici un code à six chiffres envoyé au client par SMS, que le
     * prestataire recopiait. Ce code faisait exactement le travail que fait déjà la preuve de
     * présence — et moins bien, puisqu'un SMS se transmet à distance alors qu'un code affiché se
     * lit sur place. Le faire saisir une seconde fois n'apportait rien.
     *
     * Effet de bord, jamais bloquant : la présence est un FAIT déjà gravé quand on arrive ici.
     * Une mission introuvable, un prestataire non assigné ou un incident de notification ne
     * doivent pas transformer une confirmation réussie en erreur — le prestataire, lui, est bien
     * chez le client. La réponse dit ce qui s'est passé plutôt que de le taire.
     */
    protected function startMissionOnPresence(TripTrackingSession $session, $provider): bool
    {
        $mission = Mission::query()->where('booking_id', $session->booking_id)->first();

        // Seul un état antérieur au démarrage est concerné : rejouer sur une mission déjà
        // commencée écraserait `actual_start_at`, donc la durée facturée.
        if (! $mission || ! in_array($mission->status, [MissionStatus::EN_ROUTE, MissionStatus::ARRIVED], true)) {
            return false;
        }

        try {
            app(MissionLifecycleService::class)->validateStartCodeFromQr(
                $mission,
                $provider,
                $session->last_lat,
                $session->last_lng,
            );

            return true;
        } catch (\Throwable $e) {
            Log::warning('Démarrage automatique de mission impossible après confirmation de présence.', [
                'session_id' => $session->id,
                'mission_id' => $mission->id,
                'reason' => $e->getMessage(),
            ]);

            return false;
        }
    }

    protected function presentSession(TripTrackingSession $s): array
    {
        return [
            'id' => $s->id,
            'code' => $s->code,
            'booking_id' => (int) $s->booking_id,
            'status' => $s->status,
            'destination' => [
                'lat' => $s->destination_lat,
                'lng' => $s->destination_lng,
            ],
            'last_position' => [
                'lat' => $s->last_lat,
                'lng' => $s->last_lng,
                'speed_mps' => $s->last_speed_mps,
                'ping_at' => $s->last_ping_at,
            ],
            'eta_seconds' => $s->current_eta_seconds,
            'total_distance_m' => (int) $s->total_distance_m,
            'points_count' => (int) $s->points_count,
            'started_at' => $s->started_at,
            'arrived_at' => $s->arrived_at,
            'in_mission_at' => $s->in_mission_at,
            'ended_at' => $s->ended_at,
            'presence_confirmed_at' => $s->presence_confirmed_at,
        ];
    }

    protected function authorizeProvider(Request $request, Booking $booking): void
    {
        $user = $request->user();
        abort_unless($user, 401);
        $isProvider = (int) ($booking->employe_id ?? 0) === (int) $user->id
                   || (int) ($booking->provider_user_id ?? 0) === (int) $user->id
                   || (int) ($booking->assigned_employee_id ?? 0) === (int) $user->id;
        abort_unless($isProvider, 403, 'Not assigned to this booking.');
    }

    protected function authorizeProviderForSession(Request $request, TripTrackingSession $session): void
    {
        $user = $request->user();
        abort_unless($user, 401);
        abort_unless((int) $session->provider_user_id === (int) $user->id, 403, 'Not your session.');
    }
}

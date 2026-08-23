<?php

namespace App\Http\Controllers\Api\Client;

use App\Http\Controllers\Controller;
use App\Models\Booking;
use App\Models\TripTrackingPoint;
use App\Models\TripTrackingSession;
use App\Services\Client\SharedTrackingService;
use App\Services\Missions\MissionLifecycleService;
use App\Services\TripTracking\PresenceCodeService;
use App\Services\TripTracking\TripTrackingService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;
use RuntimeException;

/**
 * @group Client — Trip Tracking
 *
 * @authenticated
 */
class TripTrackingController extends Controller
{
    /** PARTAGER LE SUIVI (E3) — le patron « suivez ma course ». */
    public function share(Request $request, Booking $booking): JsonResponse
    {
        if ((int) $booking->client_id !== (int) $request->user()->id) {
            return response()->json(['error' => 'forbidden'], 403);
        }

        return response()->json([
            'data' => [
                'url' => app(SharedTrackingService::class)->lienPour($booking),
                // Douze heures, et pas davantage : un partage qui survit à l'intervention devient
                // un traceur, et personne ne pense à le révoquer.
                'expires_in_hours' => SharedTrackingService::VALIDITE_HEURES,
            ],
        ]);
    }

    public function currentForBooking(Request $request, Booking $booking, TripTrackingService $service): JsonResponse
    {
        if ((int) $booking->client_id !== (int) $request->user()->id) {
            return response()->json(['error' => 'forbidden'], 403);
        }

        $session = $service->activeSessionForBooking((int) $booking->id);
        if (! $session) {
            return response()->json(['data' => null]);
        }

        return response()->json([
            'data' => [
                'code' => $session->code,
                'status' => $session->status,
                'destination' => [
                    'lat' => $session->destination_lat,
                    'lng' => $session->destination_lng,
                ],
                // LE TRACÉ, quand il existe.
                'route' => $service->routePayload($session),
                'provider' => [
                    'lat' => $session->last_lat,
                    'lng' => $session->last_lng,
                    'speed_mps' => $session->last_speed_mps,
                ],
                'eta_seconds' => $session->current_eta_seconds,
                'eta_minutes' => $session->current_eta_seconds !== null
                    ? (int) ceil($session->current_eta_seconds / 60)
                    : null,
                'arrived_at' => $session->arrived_at,
                'in_mission_at' => $session->in_mission_at,
                'last_ping_at' => $session->last_ping_at,
                // Interrogé périodiquement : c'est ce champ qui fait disparaître le code de
                // l'écran client une fois le prestataire confirmé sur place.
                'presence_confirmed_at' => $session->presence_confirmed_at,
            ],
        ]);
    }

    /**
     * Issue the single-use code the client shows so the provider can confirm being on site.
     *
     * @response 200 {"data": {"session_code": "trip_abc", "code": "482951", "expires_at": "2026-07-30T18:50:00+00:00"}}
     * @response 409 {"error": "not_in_mission"}
     */
    public function issuePresenceCode(Request $request, Booking $booking, TripTrackingService $service, PresenceCodeService $codes): JsonResponse
    {
        if ((int) $booking->client_id !== (int) $request->user()->id) {
            return response()->json(['error' => 'forbidden'], 403);
        }

        $session = $service->activeSessionForBooking((int) $booking->id);
        if (! $session) {
            return response()->json(['error' => 'no_session'], 404);
        }

        // Le code n'a de sens qu'une fois l'intervention démarrée : plus tôt, il attesterait
        // d'une présence que personne n'a encore annoncée.
        if ($session->status !== TripTrackingSession::STATUS_IN_MISSION) {
            return response()->json(['error' => 'not_in_mission', 'status' => $session->status], 409);
        }

        try {
            $issued = $codes->issueFor($session);
        } catch (ValidationException $e) {
            return response()->json(['error' => 'already_confirmed', 'errors' => $e->errors()], 409);
        }

        return response()->json([
            'data' => [
                'session_id' => $session->id,
                'session_code' => $session->code,
                'code' => $issued['code'],
                'expires_at' => $issued['expires_at'],
            ],
        ]);
    }

    /**
     * Issue the code the client shows to close the mission.
     *
     * @response 200 {"data": {"mission_id": 4, "code": "731204", "expires_at": "2026-07-30T21:10:00+00:00"}}
     * @response 409 {"error": "not_started"}
     */
    public function issueCompletionCode(Request $request, Booking $booking, MissionLifecycleService $lifecycle): JsonResponse
    {
        if ((int) $booking->client_id !== (int) $request->user()->id) {
            return response()->json(['error' => 'forbidden'], 403);
        }

        $mission = $booking->missions()->latest('id')->first();
        if (! $mission) {
            return response()->json(['error' => 'no_mission'], 404);
        }

        try {
            $issued = $lifecycle->generateEndCode($mission);
        } catch (RuntimeException $e) {
            // La mission doit être démarrée : plus tôt, il n'y a pas de travail à attester.
            return response()->json([
                'error' => 'not_started',
                'status' => $mission->status,
                'message' => $e->getMessage(),
            ], 409);
        }

        return response()->json([
            'data' => [
                'mission_id' => $mission->id,
                'code' => $issued['code'],
                'expires_at' => $issued['record']->expires_at,
            ],
        ]);
    }

    public function trail(Request $request, Booking $booking, TripTrackingService $service): JsonResponse
    {
        if ((int) $booking->client_id !== (int) $request->user()->id) {
            return response()->json(['error' => 'forbidden'], 403);
        }

        $session = $service->activeSessionForBooking((int) $booking->id);
        if (! $session) {
            return response()->json(['data' => []]);
        }

        $limit = (int) min(200, max(10, (int) $request->input('limit', 50)));
        $points = TripTrackingPoint::query()
            ->where('session_id', $session->id)
            ->orderByDesc('recorded_at')
            ->limit($limit)
            ->get(['lat', 'lng', 'eta_seconds', 'distance_to_dest_m', 'recorded_at']);

        return response()->json([
            'data' => $points->reverse()->values()->map(fn (TripTrackingPoint $p) => [
                'lat' => $p->lat,
                'lng' => $p->lng,
                'eta_seconds' => $p->eta_seconds,
                'distance_to_dest_m' => $p->distance_to_dest_m,
                'at' => $p->recorded_at,
            ]),
        ]);
    }
}

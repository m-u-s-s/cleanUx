<?php

namespace App\Http\Controllers\Api\Client;

use App\Http\Controllers\Controller;
use App\Models\Booking;
use App\Models\TripTrackingPoint;
use App\Models\TripTrackingSession;
use App\Services\TripTracking\PresenceCodeService;
use App\Services\TripTracking\TripTrackingService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;

/**
 * @group Client — Trip Tracking
 *
 * @authenticated
 */
class TripTrackingController extends Controller
{
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
     * The geofence proves proximity, not presence. This code is displayed as a QR by the client
     * and scanned by the provider, which requires both devices in the same place.
     *
     * A POST, not a GET: each call mints a new code and invalidates the previous one. The client
     * app must therefore call it once and hold the result — polling would rotate the code out
     * from under the provider mid-scan.
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

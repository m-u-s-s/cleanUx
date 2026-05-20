<?php

namespace App\Http\Controllers\Api\Client;

use App\Http\Controllers\Controller;
use App\Models\Booking;
use App\Models\TripTrackingPoint;
use App\Services\TripTracking\TripTrackingService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

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

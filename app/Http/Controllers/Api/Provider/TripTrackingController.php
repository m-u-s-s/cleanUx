<?php

namespace App\Http\Controllers\Api\Provider;

use App\Http\Controllers\Controller;
use App\Models\Booking;
use App\Models\TripTrackingSession;
use App\Services\TripTracking\TripTrackingService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;

class TripTrackingController extends Controller
{
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

    public function ping(Request $request, TripTrackingSession $session, TripTrackingService $service): JsonResponse
    {
        $this->authorizeProviderForSession($request, $session);

        $data = $request->validate([
            'lat' => ['required', 'numeric', 'between:-90,90'],
            'lng' => ['required', 'numeric', 'between:-180,180'],
            'accuracy_m' => ['nullable', 'numeric', 'min:0', 'max:10000'],
            'speed_mps' => ['nullable', 'numeric', 'min:0', 'max:200'],
            'heading_deg' => ['nullable', 'numeric', 'between:0,360'],
            'sequence' => ['nullable', 'string', 'max:64'],
        ]);

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

    public function end(Request $request, TripTrackingSession $session, TripTrackingService $service): JsonResponse
    {
        $this->authorizeProviderForSession($request, $session);
        $params = $request->validate([
            'reason' => ['nullable', 'string', 'max:255'],
        ]);
        $updated = $service->endSession($session, $params['reason'] ?? null);
        return response()->json(['data' => $this->presentSession($updated)]);
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

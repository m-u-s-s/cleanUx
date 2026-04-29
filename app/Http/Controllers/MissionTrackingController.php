<?php

namespace App\Http\Controllers;

use App\Models\Mission;
use App\Models\MissionTrackingSession;
use App\Services\Missions\MissionTrackingService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class MissionTrackingController extends Controller
{
    public function start(Request $request, Mission $mission, MissionTrackingService $service): JsonResponse
    {
        $this->authorize('track', $mission);

        $this->abortUnlessAssigned($mission);

        $data = $request->validate([
            'lat' => ['required', 'numeric'],
            'lng' => ['required', 'numeric'],
        ]);

        $session = $service->startToClientTracking(
            $mission,
            Auth::user(),
            (float) $data['lat'],
            (float) $data['lng']
        );

        return response()->json([
            'ok' => true,
            'session_id' => $session->id,
            'status' => $mission->fresh()->status,
        ]);
    }

    public function push(Request $request, MissionTrackingSession $session, MissionTrackingService $service): JsonResponse
    {
        abort_unless($session->employee_user_id === Auth::id(), 403);

        $data = $request->validate([
            'lat' => ['required', 'numeric'],
            'lng' => ['required', 'numeric'],
            'accuracy_meters' => ['nullable', 'numeric'],
            'speed_kmh' => ['nullable', 'numeric'],
            'heading' => ['nullable', 'numeric'],
            'battery_level' => ['nullable', 'integer', 'min:0', 'max:100'],
            'source' => ['nullable', 'string', 'max:30'],
            'app_state' => ['nullable', 'string', 'max:30'],
        ]);

        $session = $service->pushPoint($session, $data);

        return response()->json([
            'ok' => true,
            'point_count' => $session->point_count,
            'distance_meters' => $session->distance_meters,
        ]);
    }

    public function stop(Request $request, MissionTrackingSession $session, MissionTrackingService $service): JsonResponse
    {
        abort_unless($session->employee_user_id === Auth::id(), 403);

        $data = $request->validate([
            'lat' => ['nullable', 'numeric'],
            'lng' => ['nullable', 'numeric'],
        ]);

        $session = $service->stopTracking(
            $session,
            isset($data['lat']) ? (float) $data['lat'] : null,
            isset($data['lng']) ? (float) $data['lng'] : null
        );

        return response()->json([
            'ok' => true,
            'ended_at' => optional($session->ended_at)->toISOString(),
        ]);
    }

    public function live(Mission $mission, MissionTrackingService $service): JsonResponse
    {
        $this->authorize('view', $mission);
        
        $this->abortUnlessClientCanView($mission);

        return response()->json([
            'ok' => true,
            'data' => $service->livePayload($mission->fresh()),
        ]);
    }

    protected function abortUnlessAssigned(Mission $mission): void
    {
        $userId = Auth::id();

        $isAssigned = $mission->lead_employee_id === $userId
            || $mission->assignments()->where('user_id', $userId)->exists();

        abort_unless($isAssigned, 403);
    }

    protected function abortUnlessClientCanView(Mission $mission): void
    {
        $user = Auth::user();

        $isOwner =
            $mission->rendezVous?->client_id === $user?->id
            || (
                $mission->organization_account_id
                && $user?->organization_account_id
                && $mission->organization_account_id === $user->organization_account_id
            );

        abort_unless($isOwner, 403);
    }
}
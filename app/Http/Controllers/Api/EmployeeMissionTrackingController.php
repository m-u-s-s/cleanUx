<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Mission;
use App\Models\MissionTrackingSession;
use App\Models\User;
use App\Services\Missions\MissionTrackingService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class EmployeeMissionTrackingController extends Controller
{
    public function start(Request $request, Mission $mission, MissionTrackingService $service): JsonResponse
    {
        /** @var User|null $user */
        $user = Auth::user();

        abort_unless($user instanceof User, 403);
        abort_unless($user->isEmploye(), 403);

        abort_unless(
            $mission->lead_employee_id === $user->id
            || $mission->assignments()->where('user_id', $user->id)->exists(),
            403
        );

        $data = $request->validate([
            'lat' => ['required', 'numeric'],
            'lng' => ['required', 'numeric'],
        ]);

        $session = $service->startToClientTracking($mission, $user, (float) $data['lat'], (float) $data['lng']);

        return response()->json([
            'ok' => true,
            'session_id' => $session->id,
            'status' => $mission->fresh()->status,
        ]);
    }

    public function push(Request $request, MissionTrackingSession $session, MissionTrackingService $service): JsonResponse
    {
        /** @var User|null $user */
        $user = Auth::user();

        abort_unless($user instanceof User, 403);
        abort_unless($user->isEmploye(), 403);
        abort_unless($session->employee_user_id === $user->id, 403);

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
        /** @var User|null $user */
        $user = Auth::user();

        abort_unless($user instanceof User, 403);
        abort_unless($user->isEmploye(), 403);
        abort_unless($session->employee_user_id === $user->id, 403);

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
}
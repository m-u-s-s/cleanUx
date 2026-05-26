<?php

namespace App\Http\Controllers\Api\Provider;

use App\Http\Controllers\Controller;
use App\Models\FleetAssignment;
use App\Services\FleetV2\FleetService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

class FleetProviderController extends Controller
{
    public function __construct(protected FleetService $fleetService)
    {
    }

    public function myVehicles(Request $request): JsonResponse
    {
        $assignments = FleetAssignment::where('provider_user_id', $request->user()->id)
            ->where('status', FleetAssignment::STATUS_ACTIVE)
            ->with(['vehicle.certifications', 'equipment'])
            ->orderByDesc('assigned_at')
            ->get();

        return response()->json(['data' => $assignments]);
    }

    public function returnAssignment(Request $request, FleetAssignment $assignment): JsonResponse
    {
        $user = $request->user();
        abort_unless($assignment->provider_user_id === $user->id, 403, 'Not your assignment');

        $data = $request->validate([
            'condition' => 'required|in:ok,damaged,lost,needs_maintenance',
            'notes'     => 'nullable|string|max:500',
        ]);

        $assignment = $this->fleetService->returnAssignment($assignment, $data['condition'], $data['notes'] ?? null);

        if (in_array($data['condition'], ['damaged', 'lost'], true)) {
            Log::warning('Fleet alert: ' . $data['condition'] . ' reported', [
                'assignment_id' => $assignment->id,
                'provider_id'   => $user->id,
                'vehicle_id'    => $assignment->fleet_vehicle_id,
            ]);
        }

        return response()->json(['ok' => true, 'assignment' => $assignment->fresh()->load('vehicle')]);
    }
}

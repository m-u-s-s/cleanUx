<?php

namespace App\Http\Controllers;

use App\Models\Mission;
use App\Services\Missions\MissionLifecycleService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class MissionFieldActionController extends Controller
{
    public function arrived(Request $request, Mission $mission, MissionLifecycleService $service): JsonResponse
    {
        $this->authorize('update', $mission);
        
        $data = $request->validate([
            'lat' => ['nullable', 'numeric'],
            'lng' => ['nullable', 'numeric'],
        ]);

        $mission = $service->setArrived(
            $mission,
            Auth::user(),
            isset($data['lat']) ? (float) $data['lat'] : null,
            isset($data['lng']) ? (float) $data['lng'] : null
        );

        return response()->json([
            'ok' => true,
            'status' => $mission->status,
        ]);
    }
}
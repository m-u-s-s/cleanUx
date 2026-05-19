<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use App\Models\Booking;
use App\Services\Matching\MatchingV2Service;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class MatchingSimulationController extends Controller
{
    public function simulate(Request $request, Booking $booking, MatchingV2Service $service): JsonResponse
    {
        abort_unless($request->user()?->canAccessAdminModule('manage-orchestration')
            || $request->user()?->isPlatformAdmin(), 403);

        $params = $request->validate([
            'limit' => ['nullable', 'integer', 'min:1', 'max:50'],
        ]);

        $ranked = $service->topN($booking, $params['limit'] ?? 10);

        return response()->json([
            'booking_id' => $booking->id,
            'booking_reference' => $booking->booking_reference,
            'service_zone_id' => $booking->service_zone_id,
            'date' => $booking->date,
            'candidates' => $ranked->map(fn ($r) => [
                'user_id' => $r['employee']->id,
                'name' => $r['employee']->name,
                'score' => $r['score'],
                'components' => $r['breakdown']->components,
                'context' => $r['breakdown']->context,
            ])->values()->all(),
            'weights' => config('matching.weights'),
            'version' => config('matching.version'),
        ]);
    }
}

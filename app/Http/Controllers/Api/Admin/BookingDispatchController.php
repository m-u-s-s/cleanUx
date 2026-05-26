<?php

namespace App\Http\Controllers\Api\Admin;

use App\Exceptions\DispatchException;
use App\Http\Controllers\Controller;
use App\Models\Booking;
use App\Services\Dispatch\MissionDispatchService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class BookingDispatchController extends Controller
{
    public function __construct(protected MissionDispatchService $dispatcher)
    {
    }

    public function dispatch(Request $request, Booking $booking): JsonResponse
    {
        $mission = $booking->missions()->where('status', 'planned')->first();

        if (! $mission) {
            throw DispatchException::noPlannedMission();
        }

        $assignment = $this->dispatcher->dispatchToNextProvider($mission);

        return response()->json([
            'ok'            => true,
            'assignment_id' => $assignment?->id,
            'provider_id'   => $assignment?->user_id,
        ]);
    }
}

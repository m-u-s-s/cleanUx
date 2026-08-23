<?php

namespace App\Http\Controllers\Api\Provider;

use App\Http\Controllers\Controller;
use App\Models\MissionAssignment;
use App\Services\Dispatch\OfferPayloadBuilder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/** L'OFFRE EN COURS — le repli qui fait tenir tout le reste. */
class ProviderOfferController extends Controller
{
    public function __construct(
        protected OfferPayloadBuilder $payloads,
    ) {}

    public function current(Request $request): JsonResponse
    {
        $assignment = MissionAssignment::query()
            ->where('user_id', $request->user()->id)
            ->where('assignment_status', 'assigned')
            ->where(function ($q) {
                $q->whereNull('expires_at')->orWhere('expires_at', '>', now());
            })
            ->with([
                'mission',
                'mission.booking.serviceCatalog',
                'mission.booking.trade',
                'mission.booking.customer',
                'mission.booking.serviceCatalog',
                'mission.booking.trade',
                'mission.booking.customer',
            ])
            ->orderBy('expires_at')
            ->first();

        return response()->json([
            'ok' => true,
            'data' => $assignment ? $this->payloads->build($assignment) : null,
        ]);
    }
}

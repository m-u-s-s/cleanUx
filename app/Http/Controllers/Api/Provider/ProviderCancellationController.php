<?php

namespace App\Http\Controllers\Api\Provider;

use App\Http\Controllers\Controller;
use App\Models\Booking;
use App\Models\Mission;
use App\Services\Cancellation\CancelBookingService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Phase 14 — API d'annulation côté prestataire.
 *
 *   POST /api/provider/missions/{mission}/cancel       → annulation prestataire
 *   POST /api/provider/missions/{mission}/no-show      → déclarer client no-show
 */
class ProviderCancellationController extends Controller
{
    public function __construct(
        protected CancelBookingService $cancelService,
    ) {}

    public function cancel(Request $request, Mission $mission): JsonResponse
    {
        $this->authorizeProvider($request, $mission);

        $data = $request->validate([
            'reason' => ['nullable', 'string', 'max:500'],
        ]);

        $booking = $mission->booking;
        if (! $booking) {
            return response()->json(['ok' => false, 'error' => 'Mission sans booking'], 422);
        }

        try {
            $result = $this->cancelService->cancelByProvider(
                $booking,
                $request->user(),
                $data['reason'] ?? null,
            );
        } catch (\DomainException $e) {
            return response()->json(['ok' => false, 'error' => $e->getMessage()], 409);
        }

        return response()->json($result);
    }

    public function noShow(Request $request, Mission $mission): JsonResponse
    {
        $this->authorizeProvider($request, $mission);

        $booking = $mission->booking;
        if (! $booking) {
            return response()->json(['ok' => false, 'error' => 'Mission sans booking'], 422);
        }

        try {
            $result = $this->cancelService->markClientNoShow($booking, $request->user());
        } catch (\DomainException $e) {
            return response()->json(['ok' => false, 'error' => $e->getMessage()], 409);
        }

        return response()->json($result);
    }

    protected function authorizeProvider(Request $request, Mission $mission): void
    {
        $userId = $request->user()->id;

        $isLead = (int) $mission->lead_provider_user_id === (int) $userId;
        $isAssigned = $mission->assignments()
            ->where('user_id', $userId)
            ->whereIn('assignment_status', ['accepted', 'en_route', 'arrived'])
            ->exists();

        abort_if(
            ! $isLead && ! $isAssigned,
            403,
            "Vous n'êtes pas assigné à cette mission."
        );
    }
}

<?php

namespace App\Http\Controllers\Api\Client;

use App\Http\Controllers\Controller;
use App\Models\Booking;
use App\Services\Cancellation\CancelBookingService;
use App\Services\Cancellation\CancellationFeeCalculator;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Phase 14 — API d'annulation côté client mobile.
 *
 *   POST /api/client/bookings/{booking}/cancel-with-fee
 *   GET  /api/client/bookings/{booking}/cancellation-quote
 *
 * Le quote permet à l'app mobile de montrer "Tu seras facturé X€" AVANT
 * que le client confirme.
 *
 * NB : il existe déjà /cancel en Phase 12 (cancel naïf sans fee). Cet
 * endpoint le complète. Tu peux soit garder les deux, soit remplacer
 * l'ancien endpoint par celui-ci (recommandé en prod).
 */
class CancellationController extends Controller
{
    public function __construct(
        protected CancelBookingService $cancelService,
        protected CancellationFeeCalculator $calculator,
    ) {}

    public function quote(Request $request, Booking $booking): JsonResponse
    {
        $this->authorizeAccess($request, $booking);

        $details = $this->calculator->forClientCancellation($booking);

        return response()->json([
            'ok'      => true,
            'quote'   => $details,
            'price'   => (float) ($booking->estimated_price ?? 0),
            'currency'=> $booking->currency ?? 'EUR',
        ]);
    }

    public function cancelWithFee(Request $request, Booking $booking): JsonResponse
    {
        $this->authorizeAccess($request, $booking);

        $data = $request->validate([
            'reason'              => ['nullable', 'string', 'max:500'],
            'accept_fee'          => ['nullable', 'boolean'],
        ]);

        try {
            $result = $this->cancelService->cancelByClient(
                $booking,
                $request->user(),
                $data['reason'] ?? null,
            );
        } catch (\DomainException $e) {
            return response()->json(['ok' => false, 'error' => $e->getMessage()], 409);
        }

        return response()->json($result);
    }

    protected function authorizeAccess(Request $request, Booking $booking): void
    {
        $user = $request->user();
        $orgId = $user->organization_account_id ?? $user->current_organization_id ?? null;

        $isOwner = (int) ($booking->customer_user_id ?? 0) === (int) $user->id
                || (int) ($booking->client_id ?? 0) === (int) $user->id;

        $isOrgMember = $orgId
                    && $booking->customer_organization_id
                    && (int) $booking->customer_organization_id === (int) $orgId;

        $isAdmin = method_exists($user, 'isPlatformAdmin') && $user->isPlatformAdmin();

        abort_if(! $isOwner && ! $isOrgMember && ! $isAdmin, 403, "Accès refusé.");
    }
}

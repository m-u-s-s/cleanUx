<?php

namespace App\Http\Controllers\Api\Client;

use App\Http\Controllers\Controller;
use App\Models\Booking;
use App\Services\CancellationV2\CancellationEngine;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

/**
 * @group Client — Cancellation (Legacy)
 *
 * @authenticated
 *
 * Phase 14 — API d'annulation côté client mobile.
 *
 *   POST /api/client/bookings/{booking}/cancel-with-fee
 *   GET  /api/client/bookings/{booking}/cancellation-quote
 *
 * Le quote permet à l'app mobile de montrer "Tu seras facturé X€" AVANT
 * que le client confirme.
 *
 * F1 — Source de vérité unique : ces endpoints délèguent désormais au moteur
 * CancellationV2 (comme le web), pour que le montant des frais et le refund soient
 * identiques quel que soit le canal. La forme de réponse legacy
 * (ok / booking_id / fee_details / is_free) est conservée pour rétro-compatibilité.
 * La route /cancel-with-fee et CancelBookingService legacy sont conservés (retrait
 * destructif mis en quarantaine pour validation séparée).
 */
class CancellationController extends Controller
{
    public function __construct(
        protected CancellationEngine $engine,
    ) {}

    public function quote(Request $request, Booking $booking): JsonResponse
    {
        $this->authorizeAccess($request, $booking);
        $this->logLegacyUsage('cancellation-quote', $request, $booking);

        $quote = $this->engine->quote($booking->id, 'client');
        $isFree = (int) $quote->feeAmountCents === 0;

        return response()->json([
            'ok' => true,
            'quote' => [
                'fee_amount' => $quote->feeAmountCents / 100,
                'fee_percent' => (float) $quote->feePercent,
                'refund_amount' => $quote->refundAmountCents / 100,
                'is_free' => $isFree,
                'reason_code' => $quote->reasonCode,
                'currency' => $quote->currency,
            ],
            'price' => $quote->bookingAmountCents / 100,
            'currency' => $quote->currency,
        ]);
    }

    public function cancelWithFee(Request $request, Booking $booking): JsonResponse
    {
        $this->authorizeAccess($request, $booking);
        $this->logLegacyUsage('cancel-with-fee', $request, $booking);

        $data = $request->validate([
            'reason' => ['nullable', 'string', 'max:500'],
            'accept_fee' => ['nullable', 'boolean'],
        ]);

        // Route through the same V2 engine the web uses. ValidationException (e.g. terminal
        // state guard, B1) is rendered to JSON by ApiJsonRenderer globally.
        $row = $this->engine->execute(
            bookingId: $booking->id,
            actor: $request->user(),
            actorRole: 'client',
            reasonText: $data['reason'] ?? null,
        );
        $isFree = (int) $row->fee_amount_cents === 0;

        return response()->json([
            'ok' => true,
            'booking_id' => (int) $row->booking_id,
            'fee_details' => [
                'fee_amount' => $row->fee_amount_cents / 100,
                'fee_percent' => (float) $row->fee_percent_applied,
                'refund_amount' => $row->refund_amount_cents / 100,
                'is_free' => $isFree,
            ],
            'is_free' => $isFree,
        ]);
    }

    /**
     * F1 (pre-removal) — record each hit on the legacy cancellation endpoints so we can confirm
     * from the logs that no client still calls them before deleting the route + CancelBookingService
     * (the destructive step quarantined in docs/AUDIT-QUARANTINE-BATCH.md).
     */
    protected function logLegacyUsage(string $endpoint, Request $request, Booking $booking): void
    {
        Log::warning('[deprecated] legacy cancellation endpoint hit', [
            'endpoint' => $endpoint,
            'booking_id' => $booking->id,
            'user_id' => $request->user()?->id,
            'user_agent' => Str::limit((string) $request->userAgent(), 191, ''),
        ]);
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

        abort_if(! $isOwner && ! $isOrgMember && ! $isAdmin, 403, 'Accès refusé.');
    }
}

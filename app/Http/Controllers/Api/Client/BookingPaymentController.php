<?php

namespace App\Http\Controllers\Api\Client;

use App\Http\Controllers\Controller;
use App\Models\Booking;
use App\Services\Payments\MissionPaymentService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use RuntimeException;

/**
 * LE PAIEMENT MOBILE PASSE PAR LE MÊME SERVICE QUE LE WEB, ET C'EST TOUT L'OBJET DE CE FICHIER.
 *
 * @group Client — Booking Payment
 *
 * @authenticated
 */
class BookingPaymentController extends Controller
{
    public function createPaymentIntent(Request $request, Booking $booking): JsonResponse
    {
        $user = $request->user();

        // La réservation porte `client_id`, pas `user_id` — convention du dépôt.
        if ((int) $booking->client_id !== (int) $user->id) {
            return response()->json(['error' => 'forbidden'], 403);
        }

        // IDEMPOTENCE (M2) — UN SECOND INTENT EST UN SECOND DÉBIT.
        if ($booking->stripe_payment_intent_id) {
            return response()->json([
                'error' => 'payment_already_initiated',
                'message' => 'Un paiement est déjà en cours pour cette réservation.',
            ], 409);
        }

        if (in_array($booking->payment_status, ['authorized', 'captured', 'paid'], true)) {
            return response()->json([
                'error' => 'already_paid',
                'message' => 'Cette réservation est déjà payée.',
            ], 409);
        }

        if ((float) ($booking->devis_estime ?? 0) <= 0) {
            return response()->json(['error' => 'Booking has no price set.'], 422);
        }

        try {
            $intent = app(MissionPaymentService::class)->autoriserPourConfirmationClient($booking);
        } catch (RuntimeException $e) {
            // 409 ET NON 500 : « aucun prestataire assigné » est une RÈGLE, pas une panne.
            return response()->json([
                'error' => 'provider_not_ready',
                'message' => $e->getMessage(),
            ], 409);
        }

        $booking->refresh();

        return response()->json([
            'client_secret' => $intent->client_secret,
            'payment_intent_id' => $intent->id,
            // Le montant vient désormais du CALCUL DE COMMISSION, seule source de vérité du split.
            // L'ancien code recalculait `devis_estime * 100` de son côté : deux arrondis
            // indépendants sur le même prix finissent toujours par diverger d'un centime.
            'amount' => round($booking->payment_amount_cents / 100, 2),
            'amount_cents' => (int) $booking->payment_amount_cents,
            'currency' => strtolower($booking->pricing_snapshot['currency'] ?? 'eur'),
        ]);
    }
}

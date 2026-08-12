<?php

namespace App\Http\Controllers\Api\Client;

use App\Http\Controllers\Controller;
use App\Models\Booking;
use App\Services\Payments\MissionPaymentService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use RuntimeException;

/**
 * @group Client — Booking Payment
 *
 * @authenticated
 *
 * LE PAIEMENT MOBILE PASSE PAR LE MÊME SERVICE QUE LE WEB, ET C'EST TOUT L'OBJET DE CE FICHIER.
 *
 * CE QUI SE PASSAIT AVANT. Ce contrôleur fabriquait son PaymentIntent à la main. Il lui manquait
 * `transfer_data.destination` et `application_fee_amount` — donc la plateforme encaissait 100 % de
 * la course et le prestataire n'était jamais payé. Sa capture était `automatic` — donc l'argent
 * partait à la commande, avant que le travail soit fait. Et l'identifiant de l'intent n'était
 * jamais écrit sur la réservation — donc `StripeWebhookHandlers` ne retrouvait pas le booking et
 * aucun statut de paiement ne remontait. Trois défauts d'argent sur un seul chemin.
 *
 * ET CE CHEMIN EST VIVANT. On aurait pu croire l'inverse : l'application mobile réserve par la
 * WebView `/commander`, pas par l'API native (`useCreateBooking` existe mais aucun écran ne
 * l'appelle). Le PAIEMENT, lui, est bien natif — `BookingDetailScreen` navigue vers
 * `PaymentCheckout`, qui appelle ce point d'entrée. Un client qui réserve puis appuie sur « Payer »
 * passait donc par ici.
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

        /*
         * IDEMPOTENCE (M2) — UN SECOND INTENT EST UN SECOND DÉBIT.
         *
         * Rien n'empêchait le client de rappeler ce point d'entrée : un double appui, un retour
         * arrière puis un nouvel appui, une reprise de connexion. Chaque appel créait une nouvelle
         * empreinte sur la carte, et deux empreintes capturables valent deux prélèvements.
         *
         * On refuse dès qu'un intent existe déjà, plutôt que de rendre l'ancien : rendre un
         * `client_secret` déjà confirmé ferait échouer la feuille de paiement avec un message
         * incompréhensible, là où un refus explicite se lit.
         */
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
            /*
             * 409 ET NON 500 : « aucun prestataire assigné » est une RÈGLE, pas une panne. Un 500
             * ferait retenter l'application et remonterait dans Sentry comme un incident.
             */
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

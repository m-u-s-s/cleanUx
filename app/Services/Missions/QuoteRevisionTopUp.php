<?php

namespace App\Services\Missions;

use App\Models\MissionQuoteRevision;
use App\Services\Payments\CommissionService;
use Illuminate\Support\Facades\Log;
use Stripe\PaymentIntent;

/**
 * LE COMPLÉMENT — la seule classe de la révision qui parle à Stripe.
 *
 * ── POURQUOI UN COMPLÉMENT ET PAS UNE RÉ-AUTORISATION ────────────────────────────────────────
 *
 * Stripe capture pour MOINS que l'autorisé, jamais pour PLUS. Un devis de 50 € révisé à 300 € ne
 * peut donc pas s'encaisser sur l'empreinte existante. Deux chemins existaient :
 *
 *   annuler puis recréer  un seul objet, mais un TROU entre les deux appels : si la carte refuse
 *                         les 300 €, le prestataire est sur place sans aucune garantie ;
 *   garder et compléter   deux objets, et l'empreinte d'origine n'est jamais perdue.
 *
 * Le second a été retenu. La carte porte alors exactement le total révisé — 50 + 250 —, jamais un
 * centime de plus, et un échec du complément laisse la garantie initiale intacte.
 *
 * ── LA CLASSE EST ISOLÉE POUR ÊTRE REMPLAÇABLE EN TEST ───────────────────────────────────────
 *
 * Le reste du module — le constat, les remises, la fenêtre, l'arbitrage — se prouve au centime sans
 * jamais parler au réseau. C'est la doctrine déjà suivie par le règlement du temps supplémentaire.
 */
class QuoteRevisionTopUp
{
    public function __construct(
        private readonly CommissionService $commissions,
    ) {}

    /**
     * Autorise le complément sur la carte déjà utilisée pour cette réservation.
     *
     * @return array{ok: bool, intent_id: ?string, error: ?string}
     */
    public function autoriser(MissionQuoteRevision $revision, ?string $paymentMethodId = null): array
    {
        $complement = $revision->complementCents();

        if ($complement <= 0) {
            // Une révision À LA BAISSE ne demande rien de plus : la clôture capturera partiellement
            // l'empreinte d'origine et Stripe libérera le solde.
            return ['ok' => true, 'intent_id' => null, 'error' => null];
        }

        $reservation = $revision->booking;
        $prestataire = $reservation->assignedProvider ?? $reservation?->employe;
        $client = $reservation?->client;

        if ($reservation === null || $client?->stripe_id === null
            || ! $prestataire?->canReceiveStripeConnectPayments()) {
            return [
                'ok' => false,
                'intent_id' => null,
                'error' => 'Compte de paiement indisponible : le complément ne peut pas être ouvert.',
            ];
        }

        $carte = $paymentMethodId ?? $this->carteDeLaReservation($reservation->stripe_payment_intent_id);

        if ($carte === null) {
            return ['ok' => false, 'intent_id' => null, 'error' => 'Aucun moyen de paiement réutilisable.'];
        }

        try {
            $commission = $this->commissions->calculateForAmount(
                $complement, $prestataire, $revision->currency,
            );

            $intent = PaymentIntent::create([
                'amount' => $complement,
                'currency' => strtolower($revision->currency),
                'customer' => $client->stripe_id,
                'payment_method' => $carte,
                'confirm' => true,
                // MANUELLE, comme l'empreinte d'origine : on encaisse à la clôture, pas avant. Une
                // capture automatique prendrait l'argent avant que le travail soit fait.
                'capture_method' => 'manual',
                'application_fee_amount' => $commission['platform_fee_cents'],
                'transfer_data' => ['destination' => (string) $prestataire->stripe_connect_account_id],
                'metadata' => [
                    'mission_quote_revision_id' => (string) $revision->id,
                    'mission_id' => (string) $revision->mission_id,
                    'booking_reference' => (string) ($reservation->booking_reference ?? ''),
                    'original_total_cents' => (string) $revision->original_total_cents,
                    'revised_total_cents' => (string) $revision->revised_total_cents,
                ],
            ]);

            /*
             * `requires_capture` EST LE SUCCÈS ATTENDU d'une capture manuelle — pas `succeeded`.
             * Exiger le second refuserait toutes les autorisations réussies, et la révision
             * tomberait en `payment_failed` alors que l'argent est bien bloqué.
             */
            $statut = (string) ($intent->status ?? '');

            if (! in_array($statut, ['requires_capture', 'succeeded'], true)) {
                return ['ok' => false, 'intent_id' => $intent->id, 'error' => 'Autorisation non aboutie : '.$statut];
            }

            return ['ok' => true, 'intent_id' => $intent->id, 'error' => null];
        } catch (\Throwable $e) {
            Log::warning('Révision de devis : complément non autorisé', [
                'revision_id' => $revision->id,
                'raison' => $e->getMessage(),
            ]);

            return ['ok' => false, 'intent_id' => null, 'error' => $e->getMessage()];
        }
    }

    /**
     * La carte DÉJÀ utilisée pour cette réservation — jamais « une » carte du client.
     *
     * Débiter celle qu'il n'a pas choisie pour cette commande est une réclamation garantie. Même
     * règle que le règlement du temps supplémentaire.
     */
    private function carteDeLaReservation(?string $intentDOrigine): ?string
    {
        if ($intentDOrigine === null) {
            return null;
        }

        try {
            $origine = PaymentIntent::retrieve($intentDOrigine);
            $methode = $origine->payment_method ?? null;

            return is_string($methode) ? $methode : ($methode->id ?? null);
        } catch (\Throwable) {
            return null;
        }
    }
}

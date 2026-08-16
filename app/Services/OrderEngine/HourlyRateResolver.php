<?php

namespace App\Services\OrderEngine;

use App\Models\Booking;
use App\Models\Trade;
use App\Models\TradeZonePricing;

/**
 * COMBIEN VAUT UNE HEURE — une seule réponse, un seul endroit.
 *
 * Deux questions distinctes vivent ici, et les confondre coûterait cher :
 *
 *   `tarifCatalogue()` — le tarif AFFICHÉ avant la commande : zone si elle surcharge, sinon métier.
 *       C'est lui qui multiplie les heures choisies par le client.
 *
 *   `tarifEffectifDeLaReservation()` — le tarif RÉELLEMENT PAYÉ sur une réservation déjà passée,
 *       multiplicateurs compris. Il ne se lit nulle part : il se DÉDUIT du montant et de la durée.
 *
 * POURQUOI LE SECOND SE DÉDUIT AU LIEU DE SE LIRE. Le moteur applique ses multiplicateurs — immédiat
 * ×1,30, majoration de zone, options multiplicatives — puis les OUBLIE : `pricing_snapshot.lines`
 * ne conserve que les impacts additifs, et le produit des coefficients n'est persisté nulle part.
 * En revanche la durée, elle, n'est jamais multipliée. Le quotient `montant ÷ heures` rend donc
 * exactement le tarif horaire tout compris de cette réservation-là.
 *
 * C'est ce qui permet de facturer une heure supplémentaire « au tarif horaire × 1,30, même quand la
 * prestation est déjà majorée » : le ×1,30 s'empile sur un tarif qui contient déjà les autres.
 */
class HourlyRateResolver
{
    /**
     * Le tarif horaire du catalogue, en centimes. `null` si le métier ne se facture pas à l'heure
     * ou si personne n'a saisi de tarif.
     *
     * La zone PRIME sur le métier — même règle que `base_rate_cents` et `price_per_km_cents` : le
     * prix vendu vit par zone, le métier ne porte qu'une référence.
     */
    public function tarifCatalogue(Trade $trade, ?int $serviceZoneId = null): ?int
    {
        if (! $trade->hourly_billing) {
            return null;
        }

        if ($serviceZoneId !== null) {
            $surcharge = TradeZonePricing::query()
                ->where('trade_id', $trade->id)
                ->where('service_zone_id', $serviceZoneId)
                ->value('price_per_hour_cents');

            /*
             * `!== null` et non `filled()` : une zone peut délibérément poser 0 — « une heure est
             * offerte ici ». `filled(0)` vaut faux et ferait retomber sur le tarif du métier, ce
             * qui transformerait une gratuité voulue en facturation pleine.
             */
            if ($surcharge !== null) {
                return (int) $surcharge;
            }
        }

        $reference = $trade->default_hourly_rate;

        if ($reference === null || (float) $reference <= 0.0) {
            return null;
        }

        return (int) round((float) $reference * 100);
    }

    /**
     * Le tarif horaire RÉELLEMENT payé sur cette réservation, en centimes — multiplicateurs inclus.
     *
     * Rend `null` quand le quotient n'aurait aucun sens : pas de montant, pas de durée, ou métier
     * qui ne se facture pas au temps passé. Sur un forfait ou un prix au m², la division resterait
     * arithmétiquement valide et commercialement absurde — on refuse plutôt que de rendre un
     * nombre que quelqu'un finirait par facturer.
     */
    public function tarifEffectifDeLaReservation(Booking $booking): ?int
    {
        if (! $this->seFactureALHeure($booking)) {
            return null;
        }

        $minutes = (int) ($booking->duree_estimee ?? $booking->estimated_duration_minutes ?? 0);

        if ($minutes <= 0) {
            return null;
        }

        $montantCents = $this->montantFactureCents($booking);

        if ($montantCents <= 0) {
            return null;
        }

        return (int) round($montantCents / ($minutes / 60));
    }

    /**
     * Le montant sur lequel raisonner, en centimes.
     *
     * `payment_amount_cents` d'abord : c'est ce qui a été RÉELLEMENT autorisé sur la carte, donc la
     * seule vérité opposable. `devis_estime` ensuite, pour les réservations non encore payées — en
     * sachant qu'il porte le PLANCHER de la fourchette (choix assumé de `OrderConfirmationService`),
     * donc le tarif déduit est un plancher lui aussi. Sous-facturer un dépassement vaut mieux que
     * le sur-facturer sur une estimation haute que le client n'a jamais vue.
     */
    public function montantFactureCents(Booking $booking): int
    {
        $autorise = (int) ($booking->payment_amount_cents ?? 0);

        if ($autorise > 0) {
            return $autorise;
        }

        $devis = $booking->devis_estime ?? $booking->estimated_price ?? 0;

        return (int) round((float) $devis * 100);
    }

    /**
     * Cette réservation est-elle facturée au temps passé ?
     *
     * On interroge le MÉTIER de la réservation, pas une copie posée sur la réservation : le
     * discriminant reste unique. Contrepartie assumée — décocher la case sur un métier change la
     * nature des réservations déjà vendues. C'est le comportement voulu ici : contrairement à une
     * course (dont le point d'arrivée est figé sur la réservation), le mode de facturation d'une
     * mission pas encore réalisée doit suivre la décision courante de l'administrateur.
     */
    public function seFactureALHeure(Booking $booking): bool
    {
        $tradeId = $booking->resolveTradeId();

        if ($tradeId === null) {
            return false;
        }

        return (bool) Trade::query()->whereKey($tradeId)->value('hourly_billing');
    }
}

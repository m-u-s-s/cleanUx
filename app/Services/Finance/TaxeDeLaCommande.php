<?php

namespace App\Services\Finance;

use App\Models\Booking;
use App\Models\OrderDraft;
use App\Models\ServiceZone;
use App\Services\International\CountryMarketResolver;

/**
 * LA TVA D'UNE COMMANDE, RÉPONDUE UNE SEULE FOIS.
 *
 * LE PRIX DU SERVICE EST HORS TAXE. La TVA s'y ajoute au moment de la demande, au taux que le
 * super-administrateur ou le comptable règle pour le pays (`/admin/international` →
 * `country_billing_profiles.default_tax_rate`), et le lieu de l'intervention désigne ce pays :
 * la résolution passe par la zone, puis le code postal, puis la société du client.
 *
 * POURQUOI UNE SEULE PLACE. Le taux était lu à trois endroits qui ne se parlaient pas : la page
 * de confirmation ne l'affichait pas du tout, Stripe encaissait le HT, et la facture réclamait
 * le TTC. Le client payait 100 € contre une facture de 121 €, et les 21 € restaient dus à vie —
 * `recordPayment` n'ayant que des appelants manuels, aucun encaissement ne soldait jamais rien.
 *
 * LE TAUX SE FIGE AU DEVIS. Une commande conclue à 21 % le reste : changer le réglage ne doit
 * jamais rouvrir une facture déjà émise. Il est donc gelé dans `pricing_snapshot`, la même
 * colonne qui porte déjà `devis_estime`, et relu de là en priorité.
 */
class TaxeDeLaCommande
{
    public function __construct(private readonly CountryMarketResolver $marches) {}

    /**
     * Le taux applicable à un brouillon en cours, avant qu'aucune réservation n'existe.
     *
     * `OrderDraft` ne porte pas de relation vers la zone, seulement `service_zone_id` : on la
     * charge ici plutôt que d'ajouter une relation pour un seul appelant.
     */
    public function tauxPourLeBrouillon(OrderDraft $brouillon): float
    {
        $brouillon->loadMissing('client.organizationAccount.country');

        $zone = $brouillon->service_zone_id
            ? ServiceZone::query()->with('country')->find($brouillon->service_zone_id)
            : null;

        return $this->marches->effectiveTaxRate(
            $this->marches->resolveForBooking($brouillon->client, null, $zone),
        );
    }

    /**
     * Le taux d'une réservation : celui gelé au devis s'il existe, sinon celui du marché.
     *
     * Le repli n'est pas un défaut : les réservations créées avant ce gel n'en portent pas, et
     * leur facture doit rester émettable.
     */
    public function tauxPourLaReservation(Booking $reservation): float
    {
        $gele = data_get((array) ($reservation->pricing_snapshot ?? []), 'tax_rate');

        if ($gele !== null && $gele !== '') {
            return round((float) $gele, 2);
        }

        return $this->marches->effectiveTaxRate(
            $this->marches->resolveForRendezVous($reservation),
            $reservation,
        );
    }

    /**
     * Le découpage d'un montant HT, en centimes.
     *
     * LA PART DE TVA EST OBTENUE PAR SOUSTRACTION, jamais recalculée : c'est la seule façon que
     * HT + TVA fasse exactement le TTC, au centime, quel que soit l'arrondi.
     *
     * @return array{taux: float, ht_cents: int, tva_cents: int, ttc_cents: int}
     */
    public function decoupage(int $htCents, float $taux): array
    {
        $htCents = max(0, $htCents);
        $ttcCents = (int) round($htCents * (1 + max(0.0, $taux) / 100));

        return [
            'taux' => round($taux, 2),
            'ht_cents' => $htCents,
            'tva_cents' => $ttcCents - $htCents,
            'ttc_cents' => $ttcCents,
        ];
    }

    /**
     * Le découpage d'une réservation, prêt à afficher comme à encaisser.
     *
     * @return array{taux: float, ht_cents: int, tva_cents: int, ttc_cents: int}
     */
    public function pourLaReservation(Booking $reservation, ?int $htCents = null): array
    {
        $ht = $htCents ?? (int) round((float) ($reservation->devis_estime ?? $reservation->estimated_price ?? 0) * 100);

        return $this->decoupage($ht, $this->tauxPourLaReservation($reservation));
    }
}

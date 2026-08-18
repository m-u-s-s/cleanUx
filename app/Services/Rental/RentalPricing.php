<?php

namespace App\Services\Rental;

use App\Models\RentalVehicle;
use App\Support\International\Devise;
use Carbon\CarbonInterface;

/**
 * CE QUE COÛTE UNE LOCATION — AVEC ET SANS GARANTIE, TOUJOURS LES DEUX.
 *
 * Les deux chiffres sont calculés ensemble et rendus ensemble, jamais l'un sans l'autre. C'est ce
 * que demande la confirmation, mais c'est surtout ce qui rend la garantie compréhensible : un
 * supplément par jour ne veut rien dire seul. En regard de la caution qu'il fait tomber, il devient
 * un arbitrage que le client peut faire.
 *
 * ── LE NOMBRE DE JOURS EST UNE DÉCISION, PAS UNE SOUSTRACTION ────────────────────────────────
 *
 * Toutes les agences facturent la journée ENTAMÉE. Rendre à 9 h le lendemain d'un retrait à 8 h,
 * c'est deux jours, pas 1,04. Un `diffInDays` rendrait 1 et facturerait une journée de moins que
 * ce que la voiture a réellement été immobilisée — sur une flotte, c'est une fuite silencieuse.
 * On arrondit donc au jour supérieur, et jamais en dessous du minimum du véhicule.
 */
class RentalPricing
{
    /**
     * Le devis complet, dans les deux hypothèses.
     *
     * @return array{
     *     days: int,
     *     currency: string,
     *     daily_price_cents: int,
     *     sans_garantie: array{total_cents: int, deposit_cents: int},
     *     avec_garantie: array{total_cents: int, deposit_cents: int, supplement_cents: int},
     *     propose_une_garantie: bool
     * }
     */
    public function devis(RentalVehicle $vehicule, ?CarbonInterface $debut, ?CarbonInterface $fin): array
    {
        $jours = $this->joursFactures($vehicule, $debut, $fin);

        $sansGarantie = $vehicule->totalSansGarantie($jours);
        $avecGarantie = $vehicule->totalAvecGarantie($jours);

        return [
            'days' => $jours,
            // La devise du véhicule, jamais une constante : une agence marocaine facture en dirhams.
            'currency' => Devise::premiereRenseignee($vehicule->currency),
            'daily_price_cents' => $vehicule->daily_price_cents,
            'sans_garantie' => [
                'total_cents' => $sansGarantie,
                'deposit_cents' => $vehicule->cautionPour(RentalVehicle::PROTECTION_SANS),
            ],
            'avec_garantie' => [
                'total_cents' => $avecGarantie,
                'deposit_cents' => $vehicule->cautionPour(RentalVehicle::PROTECTION_AVEC),
                'supplement_cents' => max(0, $avecGarantie - $sansGarantie),
            ],
            'propose_une_garantie' => $vehicule->proposeUneGarantie(),
        ];
    }

    /**
     * LE NOMBRE DE JOURS FACTURÉS.
     *
     * Toute journée entamée est due — c'est la règle de toutes les agences, et elle protège la
     * disponibilité autant que la recette : une voiture rendue à 18 h n'est pas relouable le même
     * jour. `ceil` sur les heures, donc, jamais `diffInDays`.
     *
     * Le minimum du véhicule s'applique ensuite : une location de deux heures sur un véhicule à
     * trois jours minimum en facture trois.
     */
    public function joursFactures(RentalVehicle $vehicule, ?CarbonInterface $debut, ?CarbonInterface $fin): int
    {
        $minimum = max(1, (int) $vehicule->min_rental_days);

        if ($debut === null || $fin === null || $fin->lessThanOrEqualTo($debut)) {
            return $minimum;
        }

        $heures = $debut->diffInMinutes($fin) / 60;

        return max($minimum, (int) ceil($heures / 24));
    }

    /**
     * Le total à retenir pour la protection choisie.
     *
     * @return array{days: int, subtotal_cents: int, waiver_total_cents: int, total_cents: int, deposit_cents: int, currency: string}
     */
    public function pour(RentalVehicle $vehicule, ?CarbonInterface $debut, ?CarbonInterface $fin, string $protection): array
    {
        $devis = $this->devis($vehicule, $debut, $fin);
        $avecGarantie = $protection === RentalVehicle::PROTECTION_AVEC;

        $sousTotal = $devis['sans_garantie']['total_cents'];
        $garantie = $avecGarantie ? $devis['avec_garantie']['supplement_cents'] : 0;

        return [
            'days' => $devis['days'],
            'subtotal_cents' => $sousTotal,
            'waiver_total_cents' => $garantie,
            'total_cents' => $sousTotal + $garantie,
            'deposit_cents' => $vehicule->cautionPour($protection),
            'currency' => $devis['currency'],
        ];
    }
}

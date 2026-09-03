<?php

namespace App\Services\Commission;

use App\Models\CommissionRule;

/**
 * LE TAUX QUI S'APPLIQUE, ET QUI L'A DÉCIDÉ.
 *
 * Rendre un simple `float` obligerait chaque appelant à redemander la règle pour expliquer le
 * chiffre — sur un écran, sur une facture, dans un litige six mois plus tard. La raison voyage
 * donc avec le taux.
 */
final readonly class TauxDeCommission
{
    public function __construct(
        /** La fraction appliquée, de 0.0 à 1.0. */
        public float $taux,
        /** Le plancher en centimes ; 0 rend une commission réellement gratuite. */
        public int $planchercents,
        public ?CommissionRule $regle,
        /** D'où vient ce taux, en clair, pour l'afficher. */
        public string $origine,
    ) {}

    public function pourcentage(): float
    {
        return round($this->taux * 100, 2);
    }

    /** LA NOTE AFFICHÉE SUR LES ÉCRANS — elle dit le chiffre ET sa raison. */
    public function note(): string
    {
        $pourcentage = rtrim(rtrim(number_format($this->pourcentage(), 2, ',', ' '), '0'), ',');

        if ($this->taux <= 0.0) {
            return 'Aucune commission — '.$this->origine;
        }

        return $pourcentage.' % de commission — '.$this->origine;
    }
}

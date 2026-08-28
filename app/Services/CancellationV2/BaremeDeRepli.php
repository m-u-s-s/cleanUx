<?php

namespace App\Services\CancellationV2;

/**
 * Les paliers de `config/cancellation.php`, servis au moteur quand aucune politique en base
 * ne repond. Une seule implementation d'annulation, deux sources de bareme.
 */
class BaremeDeRepli
{
    /**
     * @param  ?int  $minutesAvant  Minutes avant le debut, ou null si l'horaire est inconnu.
     * @return array{fee_percent: float, fee_flat_cents: int, min_fee_cents: int, label: string}|null
     */
    public function pour(string $actorRole, ?int $minutesAvant): ?array
    {
        return $actorRole === 'provider'
            ? $this->pourLePrestataire($minutesAvant)
            : $this->pourLeClient($minutesAvant);
    }

    /** @return array{fee_percent: float, fee_flat_cents: int, min_fee_cents: int, label: string}|null */
    private function pourLeClient(?int $minutesAvant): ?array
    {
        $config = (array) config('cancellation.client');
        $paliers = array_values((array) ($config['fee_tiers'] ?? []));

        if ($paliers === []) {
            return null;
        }

        $plancher = (int) round(((float) ($config['minimum_fee_eur'] ?? 0)) * 100);

        // Horaire inconnu : le dernier palier, comme le service historique.
        if ($minutesAvant === null) {
            $dernier = (array) $paliers[count($paliers) - 1];

            return $this->palier((float) ($dernier['fee_percent'] ?? 100), $plancher, 'repli — horaire inconnu');
        }

        foreach ($paliers as $palier) {
            $palier = (array) $palier;
            $seuil = isset($palier['min_hours_before'])
                ? ((int) $palier['min_hours_before']) * 60
                : (int) ($palier['min_minutes_before'] ?? 0);

            if ($minutesAvant >= $seuil) {
                $pourcent = (float) ($palier['fee_percent'] ?? 0);

                return $this->palier($pourcent, $plancher, $this->libelle($seuil, $pourcent));
            }
        }

        return $this->palier(100.0, $plancher, 'repli — au-dela du dernier palier');
    }

    /** @return array{fee_percent: float, fee_flat_cents: int, min_fee_cents: int, label: string} */
    private function pourLePrestataire(?int $minutesAvant): array
    {
        $config = (array) config('cancellation.provider');
        $fenetre = (int) ($config['free_cancellation_minutes'] ?? 30);

        if ($minutesAvant !== null && $minutesAvant >= $fenetre) {
            return $this->palier(0.0, 0, 'repli — hors fenetre, aucune penalite');
        }

        return [
            'fee_percent' => 0.0,
            'fee_flat_cents' => (int) round(((float) ($config['penalty_eur'] ?? 0)) * 100),
            'min_fee_cents' => 0,
            'label' => 'repli — penalite fixe',
        ];
    }

    /** @return array{fee_percent: float, fee_flat_cents: int, min_fee_cents: int, label: string} */
    private function palier(float $pourcent, int $plancher, string $libelle): array
    {
        return [
            'fee_percent' => $pourcent,
            'fee_flat_cents' => 0,
            'min_fee_cents' => $plancher,
            'label' => $libelle,
        ];
    }

    private function libelle(int $seuilMinutes, float $pourcent): string
    {
        $delai = $seuilMinutes >= 60
            ? sprintf('%dh', intdiv($seuilMinutes, 60))
            : sprintf('%dmin', $seuilMinutes);

        return sprintf('repli — ≥%s : %.0f%%', $delai, $pourcent);
    }
}

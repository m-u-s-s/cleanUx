<?php

namespace App\Services\KybV2;

use App\Models\BusinessBeneficialOwner;
use App\Models\BusinessDocument;
use App\Models\BusinessEntity;
use App\Models\BusinessSanctionsCheck;
use App\Models\BusinessVerification;

class RiskScoreEngine
{
    /**
     * Calcule un score de risque 0-100 + risk_level basé sur le state actuel de l'entité.
     * Convention :
     *   - Plus le score est haut, PLUS le risque est élevé.
     *   - Score 0 = idéal, score 100 = critique.
     */
    public function compute(BusinessEntity $entity): array
    {
        $weights = (array) config('kyb_v2.risk_weights', []);
        $score = 0.0;
        $reasons = [];

        // 1) Sanctions check (le plus lourd)
        $hasSanctions = $entity->sanctionsChecks()->matches()->exists();
        if ($hasSanctions) {
            $score += (float) ($weights['sanctions_match'] ?? 50);
            $reasons[] = 'sanctions_match';
        }

        // 2) PEP owner
        $hasPep = $entity->beneficialOwners()
            ->where('is_pep', true)
            ->exists();
        if ($hasPep) {
            $score += (float) ($weights['pep_owner'] ?? 25);
            $reasons[] = 'pep_owner';
        }

        // 3) Missing kbis / certificate
        $hasKbis = $entity->documents()
            ->whereIn('document_type', ['kbis', 'certificate_incorp'])
            ->where('status', BusinessDocument::STATUS_APPROVED)
            ->exists();
        if (! $hasKbis) {
            $score += (float) ($weights['missing_kbis'] ?? 10);
            $reasons[] = 'missing_kbis';
        }

        // 4) Recent incorporation (< 1 an)
        if ($entity->incorporation_date && $entity->incorporation_date->isAfter(now()->subYear())) {
            $score += (float) ($weights['recent_incorporation'] ?? 8);
            $reasons[] = 'recent_incorporation';
        }

        // 5) VAT non vérifiée
        $hasVerifiedVat = $entity->verifications()
            ->where('check_type', 'tax_validity')
            ->where('status', BusinessVerification::STATUS_SUCCESS)
            ->exists();
        if ($entity->vat_id && ! $hasVerifiedVat) {
            $score += (float) ($weights['unverified_vat'] ?? 5);
            $reasons[] = 'unverified_vat';
        }

        // 6) Pays risque
        $highRiskCountries = (array) config('kyb_v2.high_risk_countries', []);
        if (in_array($entity->country_code, $highRiskCountries, true)) {
            $score += (float) ($weights['high_risk_country'] ?? 15);
            $reasons[] = 'high_risk_country';
        }

        $score = min(100.0, max(0.0, round($score, 2)));
        $level = $this->resolveLevel($score);

        return [
            'score' => $score,
            'level' => $level,
            'reasons' => $reasons,
        ];
    }

    public function resolveLevel(float $score): string
    {
        $t = (array) config('kyb_v2.risk_thresholds', []);
        $lowMax = (float) ($t['low_max'] ?? 20);
        $medMax = (float) ($t['medium_max'] ?? 50);
        $highMax = (float) ($t['high_max'] ?? 75);

        if ($score <= $lowMax) {
            return BusinessEntity::RISK_LOW;
        }
        if ($score <= $medMax) {
            return BusinessEntity::RISK_MEDIUM;
        }
        if ($score <= $highMax) {
            return BusinessEntity::RISK_HIGH;
        }
        return BusinessEntity::RISK_CRITICAL;
    }
}

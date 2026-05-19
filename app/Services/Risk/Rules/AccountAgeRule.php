<?php

namespace App\Services\Risk\Rules;

use App\Services\Risk\RiskContext;
use App\Services\Risk\RiskRuleHit;
use App\Services\Risk\RiskRuleInterface;

/**
 * Flag les comptes très récents (signal classique de fraude : compte créé,
 * action sensible immédiate).
 */
class AccountAgeRule implements RiskRuleInterface
{
    public function code(): string
    {
        return 'account.very_new';
    }

    public function evaluate(RiskContext $context, ?array $params = null): ?RiskRuleHit
    {
        $user = $context->user;
        if (! $user || ! $user->created_at) {
            return null;
        }

        $thresholdHours = (int) ($params['threshold_hours'] ?? 24);
        $ageHours = $user->created_at->diffInHours(now());

        if ($ageHours >= $thresholdHours) {
            return null;
        }

        // Score décroît linéairement avec l'âge
        $score = (int) (($params['max_score'] ?? 30) * (1 - $ageHours / $thresholdHours));
        if ($score <= 0) {
            return null;
        }

        return new RiskRuleHit(
            code: $this->code(),
            score: $score,
            reason: "Compte créé il y a {$ageHours}h (seuil {$thresholdHours}h)",
            details: ['age_hours' => $ageHours, 'threshold_hours' => $thresholdHours],
        );
    }
}

<?php

namespace App\Services\Risk\Rules;

use App\Services\Risk\RiskContext;
use App\Services\Risk\RiskRuleHit;
use App\Services\Risk\RiskRuleInterface;

/**
 * Évalue le risque sur la base des déclines récents (cartes refusées).
 * Lit `decline_count_last_24h` depuis $context->extra (envoyé par le caller).
 */
class PaymentDeclineRule implements RiskRuleInterface
{
    public function code(): string
    {
        return 'payment.decline_burst';
    }

    public function evaluate(RiskContext $context, ?array $params = null): ?RiskRuleHit
    {
        $count = (int) $context->get('decline_count_last_24h', 0);
        $threshold = (int) ($params['threshold'] ?? 3);

        if ($count < $threshold) {
            return null;
        }

        $score = min(80, 25 * ($count - $threshold + 1));

        return new RiskRuleHit(
            code: $this->code(),
            score: $score,
            reason: "{$count} déclines de paiement dans les dernières 24h (seuil {$threshold})",
            details: ['decline_count' => $count, 'threshold' => $threshold],
        );
    }
}

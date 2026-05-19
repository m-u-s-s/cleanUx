<?php

namespace App\Services\Risk\Rules;

use App\Services\Risk\RiskContext;
use App\Services\Risk\RiskRuleHit;
use App\Services\Risk\RiskRuleInterface;

/**
 * Détecte un mismatch entre le pays déclaré (user.country / billing) et
 * le pays infered de l'IP / event.
 *
 * Lit `expected_country_code` et `observed_country_code` depuis $context->extra.
 * Le caller doit avoir résolu le pays de l'IP (via MaxMind, header CF, etc.)
 * avant d'appeler.
 */
class GeoMismatchRule implements RiskRuleInterface
{
    public function code(): string
    {
        return 'geo.country_mismatch';
    }

    public function evaluate(RiskContext $context, ?array $params = null): ?RiskRuleHit
    {
        $expected = strtoupper((string) $context->get('expected_country_code', ''));
        $observed = strtoupper((string) $context->get('observed_country_code', ''));

        if ($expected === '' || $observed === '' || $expected === $observed) {
            return null;
        }

        return new RiskRuleHit(
            code: $this->code(),
            score: (int) ($params['score'] ?? 25),
            reason: "Pays IP ({$observed}) ≠ pays déclaré ({$expected})",
            details: ['expected' => $expected, 'observed' => $observed],
        );
    }
}

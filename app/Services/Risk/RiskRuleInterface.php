<?php

namespace App\Services\Risk;

interface RiskRuleInterface
{
    /**
     * Identifiant stable de la règle (matche RiskRule::code).
     */
    public function code(): string;

    /**
     * Évalue la règle pour ce contexte.
     *
     * @return RiskRuleHit|null  Null si la règle ne s'applique pas, sinon un hit avec score + raison.
     */
    public function evaluate(RiskContext $context, ?array $params = null): ?RiskRuleHit;
}

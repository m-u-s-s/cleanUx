<?php

namespace App\Services\Risk;

class RiskRuleHit
{
    public function __construct(
        public readonly string $code,
        public readonly int $score,
        public readonly string $reason,
        public readonly array $details = [],
    ) {}
}

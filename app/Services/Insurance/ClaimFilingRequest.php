<?php

namespace App\Services\Insurance;

class ClaimFilingRequest
{
    public function __construct(
        public readonly string $policyExternalId,
        public readonly string $incidentType,
        public readonly string $incidentDescription,
        public readonly \DateTimeInterface $incidentDate,
        public readonly int $amountClaimedCents,
        public readonly string $currency,
        public readonly array $evidence = [],
        public readonly ?string $idempotencyKey = null,
    ) {}
}

<?php

namespace App\Services\Insurance;

/**
 * Update typé issu d'un webhook provider externe — applique à BookingInsurance
 * ou InsuranceClaim selon le `target`.
 */
class InsuranceWebhookUpdate
{
    public const TARGET_POLICY = 'policy';
    public const TARGET_CLAIM = 'claim';

    public function __construct(
        public readonly string $target,
        public readonly string $externalId,
        public readonly string $newStatus,
        public readonly ?int $amountSettledCents = null,
        public readonly ?string $reason = null,
        public readonly array $raw = [],
    ) {}
}

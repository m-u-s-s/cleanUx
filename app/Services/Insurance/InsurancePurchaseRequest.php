<?php

namespace App\Services\Insurance;

class InsurancePurchaseRequest
{
    public function __construct(
        public readonly string $planCode,
        public readonly int $bookingId,
        public readonly int $premiumCents,
        public readonly int $coverageCents,
        public readonly string $currency,
        public readonly ?int $userId = null,
        public readonly ?int $providerUserId = null,
        public readonly ?\DateTimeInterface $effectiveFrom = null,
        public readonly ?\DateTimeInterface $effectiveUntil = null,
        public readonly ?string $idempotencyKey = null,
        public readonly array $metadata = [],
    ) {}
}

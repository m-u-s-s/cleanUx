<?php

namespace App\Services\Insurance;

class ClaimFilingResult
{
    public function __construct(
        public readonly bool $accepted,
        public readonly ?string $externalClaimId = null,
        public readonly string $status = 'filed',
        public readonly ?string $failureReason = null,
        public readonly array $raw = [],
    ) {}

    public static function accepted(string $externalId, string $status = 'filed', array $raw = []): self
    {
        return new self(accepted: true, externalClaimId: $externalId, status: $status, raw: $raw);
    }

    public static function failed(string $reason, array $raw = []): self
    {
        return new self(accepted: false, failureReason: $reason, raw: $raw);
    }
}

<?php

namespace App\Services\Insurance;

class InsurancePurchaseResult
{
    public function __construct(
        public readonly bool $accepted,
        public readonly ?string $externalId = null,
        public readonly ?string $policyNumber = null,
        public readonly ?string $failureCode = null,
        public readonly ?string $failureReason = null,
        public readonly array $raw = [],
    ) {}

    public static function accepted(string $externalId, ?string $policyNumber = null, array $raw = []): self
    {
        return new self(accepted: true, externalId: $externalId, policyNumber: $policyNumber, raw: $raw);
    }

    public static function failed(string $reason, ?string $code = null, array $raw = []): self
    {
        return new self(accepted: false, failureCode: $code, failureReason: $reason, raw: $raw);
    }
}

<?php

namespace App\Services\Insurance;

class InsuranceCancelResult
{
    public function __construct(
        public readonly bool $accepted,
        public readonly ?string $failureReason = null,
        public readonly array $raw = [],
    ) {}

    public static function ok(array $raw = []): self
    {
        return new self(accepted: true, raw: $raw);
    }

    public static function failed(string $reason, array $raw = []): self
    {
        return new self(accepted: false, failureReason: $reason, raw: $raw);
    }
}

<?php

namespace App\Services\KybV2;

class VerificationResult
{
    public function __construct(
        public readonly bool $success,
        public readonly string $provider,
        public readonly string $checkType,
        public readonly ?string $matchedValue = null,
        public readonly array $payload = [],
        public readonly ?string $error = null,
    ) {}

    public function toArray(): array
    {
        return [
            'success' => $this->success,
            'provider' => $this->provider,
            'check_type' => $this->checkType,
            'matched_value' => $this->matchedValue,
            'error' => $this->error,
        ];
    }
}

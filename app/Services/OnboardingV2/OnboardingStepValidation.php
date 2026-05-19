<?php

namespace App\Services\OnboardingV2;

class OnboardingStepValidation
{
    public function __construct(
        public readonly bool $ok,
        public readonly array $errors = [],
        public readonly array $normalizedData = [],
        public readonly array $metadata = [],
    ) {}

    public static function pass(array $normalizedData = [], array $metadata = []): self
    {
        return new self(ok: true, normalizedData: $normalizedData, metadata: $metadata);
    }

    public static function fail(array $errors, array $metadata = []): self
    {
        return new self(ok: false, errors: $errors, metadata: $metadata);
    }
}

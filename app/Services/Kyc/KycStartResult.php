<?php

namespace App\Services\Kyc;

class KycStartResult
{
    public function __construct(
        public readonly string $externalApplicantId,
        public readonly ?string $externalCheckId = null,
        public readonly ?string $hostedFlowUrl = null,
        public readonly array $raw = [],
    ) {}
}

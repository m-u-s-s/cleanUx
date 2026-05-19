<?php

namespace App\Services\Kyc;

use App\Models\User;

class KycStartRequest
{
    public function __construct(
        public readonly User $user,
        public readonly string $countryCode,
        /** @var array<int,string> */
        public readonly array $requestedChecks = [],
        public readonly array $applicantData = [],
    ) {}
}

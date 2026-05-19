<?php

namespace App\Services\Sms;

class SmsStatusUpdate
{
    public function __construct(
        public readonly string $externalId,
        public readonly string $status,
        public readonly ?string $failureCode = null,
        public readonly ?string $failureReason = null,
        public readonly array $raw = [],
    ) {}
}

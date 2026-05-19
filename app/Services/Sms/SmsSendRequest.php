<?php

namespace App\Services\Sms;

class SmsSendRequest
{
    public function __construct(
        public readonly string $toPhone,
        public readonly string $body,
        public readonly ?string $fromPhone = null,
        public readonly ?string $idempotencyKey = null,
        public readonly array $metadata = [],
    ) {}
}

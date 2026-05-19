<?php

namespace App\Services\Sms;

class SmsSendResult
{
    public function __construct(
        public readonly bool $accepted,
        public readonly ?string $externalId = null,
        public readonly string $status = 'sent',
        public readonly ?string $failureCode = null,
        public readonly ?string $failureReason = null,
        public readonly ?float $cost = null,
        public readonly array $raw = [],
    ) {}

    public static function accepted(?string $externalId, string $status = 'sent', array $raw = []): self
    {
        return new self(accepted: true, externalId: $externalId, status: $status, raw: $raw);
    }

    public static function failed(string $reason, ?string $code = null, array $raw = []): self
    {
        return new self(
            accepted: false,
            status: 'failed',
            failureCode: $code,
            failureReason: $reason,
            raw: $raw,
        );
    }
}

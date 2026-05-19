<?php

namespace App\Services\Push;

class PushSendResult
{
    public function __construct(
        public readonly bool $accepted,
        public readonly ?string $externalId = null,
        public readonly string $status = 'sent',
        public readonly ?string $failureCode = null,
        public readonly ?string $failureReason = null,
        public readonly bool $tokenInvalid = false,
        public readonly array $raw = [],
    ) {}

    public static function accepted(string $externalId, string $status = 'sent', array $raw = []): self
    {
        return new self(
            accepted: true,
            externalId: $externalId,
            status: $status,
            raw: $raw,
        );
    }

    public static function failed(string $reason, ?string $code = null, bool $tokenInvalid = false, array $raw = []): self
    {
        return new self(
            accepted: false,
            status: $tokenInvalid ? 'invalid_token' : 'failed',
            failureCode: $code,
            failureReason: $reason,
            tokenInvalid: $tokenInvalid,
            raw: $raw,
        );
    }
}

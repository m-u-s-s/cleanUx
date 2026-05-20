<?php

namespace App\Services\EmailV2;

class EmailSendResult
{
    public function __construct(
        public readonly bool $success,
        public readonly string $provider,
        public readonly ?string $providerMessageId = null,
        public readonly ?string $error = null,
        public readonly array $raw = [],
    ) {}

    public function toArray(): array
    {
        return [
            'success' => $this->success,
            'provider' => $this->provider,
            'provider_message_id' => $this->providerMessageId,
            'error' => $this->error,
        ];
    }
}

<?php

namespace App\Services\Push;

class PushSendRequest
{
    public function __construct(
        public readonly string $token,
        public readonly string $platform,
        public readonly ?string $title,
        public readonly string $body,
        public readonly array $data = [],
        public readonly ?string $idempotencyKey = null,
        public readonly string $category = 'transactional',
        public readonly ?string $locale = null,
        public readonly array $metadata = [],
    ) {}
}

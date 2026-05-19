<?php

namespace App\Services\ChatV2;

class ModerationResult
{
    public function __construct(
        public readonly string $status,   // clean | flagged | blocked
        public readonly ?string $reason,
        public readonly string $redactedBody,
        public readonly ?string $originalHash = null,
    ) {}

    public function isBlocked(): bool
    {
        return $this->status === 'blocked';
    }

    public function isFlagged(): bool
    {
        return $this->status === 'flagged';
    }

    public function isClean(): bool
    {
        return $this->status === 'clean';
    }
}

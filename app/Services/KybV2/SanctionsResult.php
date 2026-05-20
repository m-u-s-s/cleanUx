<?php

namespace App\Services\KybV2;

class SanctionsResult
{
    public function __construct(
        public readonly bool $hasMatch,
        public readonly int $matchCount,
        public readonly string $listName,
        public readonly array $matches = [],
        public readonly ?string $error = null,
        public readonly string $provider = 'mock',
    ) {}

    public function toArray(): array
    {
        return [
            'has_match' => $this->hasMatch,
            'match_count' => $this->matchCount,
            'list_name' => $this->listName,
            'provider' => $this->provider,
            'error' => $this->error,
        ];
    }
}

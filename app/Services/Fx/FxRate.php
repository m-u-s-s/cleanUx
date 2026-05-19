<?php

namespace App\Services\Fx;

class FxRate
{
    public function __construct(
        public readonly string $base,
        public readonly string $quote,
        public readonly float $rate,
        public readonly string $source,
        public readonly \DateTimeInterface $fetchedAt,
        public readonly array $raw = [],
    ) {}
}

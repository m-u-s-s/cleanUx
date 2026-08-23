<?php

namespace App\Services\FaceCheck\Data;

/** Le résultat d'un appariement avec une pièce d'identité. */
final readonly class FaceCompareResult
{
    /**
     * @param  array<string, mixed>  $raw
     */
    public function __construct(
        public bool $conclusive,
        public ?float $score = null,
        public ?string $reason = null,
        public array $raw = [],
    ) {}

    public static function inconclusive(string $reason): self
    {
        return new self(conclusive: false, reason: $reason);
    }
}

<?php

namespace App\Services\Matching;

class MatchingScoreBreakdown
{
    public function __construct(
        public readonly int $userId,
        public readonly float $totalScore,
        /** @var array<string, array{raw:float, weighted:float, weight:int}> */
        public readonly array $components,
        public readonly array $context = [],
    ) {}

    public function toArray(): array
    {
        return [
            'user_id' => $this->userId,
            'total_score' => round($this->totalScore, 2),
            'components' => $this->components,
            'context' => $this->context,
        ];
    }
}

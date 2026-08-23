<?php

namespace App\Services\Dispatch;

use App\Models\User;

/** UN CANDIDAT, ET LES DEUX SEULES CHOSES QUI DÉPARTAGENT : la distance, puis le score. */
final class DispatchCandidate
{
    public function __construct(
        public readonly User $user,
        public readonly ?int $distanceM,
        public readonly float $score,
    ) {}

    public function id(): int
    {
        return (int) $this->user->id;
    }

    /** @return array<string, mixed> */
    public function toArray(): array
    {
        return [
            'user_id' => $this->id(),
            'name' => $this->user->name,
            'distance_m' => $this->distanceM,
            'distance_km' => $this->distanceM !== null ? round($this->distanceM / 1000, 2) : null,
            'score' => $this->score,
        ];
    }
}

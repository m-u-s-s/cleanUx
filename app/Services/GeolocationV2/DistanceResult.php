<?php

namespace App\Services\GeolocationV2;

class DistanceResult
{
    public function __construct(
        public readonly int $distanceMeters,
        public readonly ?int $durationSeconds = null,
        public readonly string $mode = 'driving',
        public readonly string $provider = 'mock',
        public readonly bool $isFallbackHaversine = false,
        public readonly array $raw = [],
    ) {}

    public function distanceKm(): float
    {
        return round($this->distanceMeters / 1000, 2);
    }

    public function durationMinutes(): ?int
    {
        if ($this->durationSeconds === null) {
            return null;
        }
        return (int) ceil($this->durationSeconds / 60);
    }

    public function toArray(): array
    {
        return [
            'distance_meters' => $this->distanceMeters,
            'distance_km' => $this->distanceKm(),
            'duration_seconds' => $this->durationSeconds,
            'duration_minutes' => $this->durationMinutes(),
            'mode' => $this->mode,
            'provider' => $this->provider,
            'is_fallback_haversine' => $this->isFallbackHaversine,
        ];
    }
}

<?php

namespace App\Services\Geo;

/**
 * Une route entre deux points : ce qu'elle coûte en distance, en temps, et par où elle passe.
 *
 * `points` est la GÉOMÉTRIE — la suite de coordonnées que la carte trace. Deux points seulement
 * signifient une ligne droite : aucun fournisseur d'itinéraire n'a répondu, et `source` le dit.
 * C'est une information à afficher, pas à taire : une ligne droite qui se ferait passer pour un
 * trajet routier annoncerait un temps de course qu'aucun conducteur ne tiendra.
 */
class RouteResult
{
    /**
     * @param  list<array{lat: float, lng: float}>  $points
     */
    public function __construct(
        public readonly int $distanceMeters,
        public readonly ?int $durationSeconds,
        public readonly string $source,
        public readonly array $points = [],
    ) {}

    /** La géométrie vient-elle d'un vrai calcul d'itinéraire, ou d'une ligne droite ? */
    public function estUneLigneDroite(): bool
    {
        return count($this->points) <= 2;
    }

    public function distanceKm(): float
    {
        return round($this->distanceMeters / 1000, 1);
    }

    public function durationMinutes(): ?int
    {
        return $this->durationSeconds === null ? null : (int) ceil($this->durationSeconds / 60);
    }

    /** @return array<string, mixed> */
    public function toArray(): array
    {
        return [
            'distance_m' => $this->distanceMeters,
            'distance_km' => $this->distanceKm(),
            'duration_s' => $this->durationSeconds,
            'duration_min' => $this->durationMinutes(),
            'source' => $this->source,
            'points' => $this->points,
            'is_straight_line' => $this->estUneLigneDroite(),
        ];
    }
}

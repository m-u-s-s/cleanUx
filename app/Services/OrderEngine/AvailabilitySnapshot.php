<?php

namespace App\Services\OrderEngine;

use Illuminate\Support\Carbon;

/** Ce qu'on peut honnêtement promettre à quelqu'un qui vient de donner son adresse. */
final class AvailabilitySnapshot
{
    /**
     * @param  int  $providerCount  prestataires du métier dans le rayon
     * @param  int  $providersLocatable  ceux dont la position est connue — la base du compte ci-dessus
     * @param  list<array{trade_id: int, name: string, slug: string, provider_count: int}>  $nearbyTrades
     */
    public function __construct(
        public readonly int $providerCount,
        public readonly int $radiusM,
        public readonly ?Carbon $earliestAt = null,
        public readonly int $providersLocatable = 0,
        public readonly array $nearbyTrades = [],
        public readonly ?int $widerRadiusM = null,
        public readonly int $widerRadiusCount = 0,
    ) {}

    /** Peut-on annoncer un chiffre ? Sans position connue, non. */
    public function isTrustworthy(): bool
    {
        return $this->providersLocatable > 0;
    }

    public function hasProviders(): bool
    {
        return $this->providerCount > 0;
    }

    /** Une impasse doit toujours offrir une suite : un écran d'erreur sans action est un bug produit. */
    public function hasWayForward(): bool
    {
        return $this->hasProviders()
            || $this->widerRadiusCount > 0
            || $this->nearbyTrades !== [];
    }

    public function radiusKm(): float
    {
        return round($this->radiusM / 1000, 1);
    }
}

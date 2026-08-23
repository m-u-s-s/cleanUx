<?php

namespace App\Services\OrderEngine;

/** Le résultat d'un calcul de prix, et son explication. */
final class PriceBreakdown
{
    /**
     * @param  list<array{code: string, label: string, detail: string|null, min_cents: int, max_cents: int}>  $lines
     */
    public function __construct(
        public readonly int $minCents,
        public readonly int $maxCents,
        public readonly int $durationMin,
        public readonly array $lines = [],
        /** Aucune estimation possible : le métier exige un devis professionnel. */
        public readonly bool $quoteOnly = false,
    ) {}

    public static function quoteOnly(array $lines = []): self
    {
        return new self(0, 0, 0, $lines, true);
    }

    /** Le prix est-il certain, ou reste-t-il une fourchette ? */
    public function isExact(): bool
    {
        return ! $this->quoteOnly && $this->minCents === $this->maxCents;
    }

    /** Largeur de la fourchette — ce que le client risque encore de découvrir. */
    public function spreadCents(): int
    {
        return $this->maxCents - $this->minCents;
    }

    public function withLines(array $lines): self
    {
        return new self($this->minCents, $this->maxCents, $this->durationMin, $lines, $this->quoteOnly);
    }
}

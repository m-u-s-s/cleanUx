<?php

namespace App\Services\FaceCheck\Data;

/**
 * Le résultat d'un appariement avec une pièce d'identité.
 *
 * `conclusive = false` est un verdict à part entière, et le plus fréquent en pratique : une pièce
 * scannée de travers, un PDF au lieu d'une photo, un portrait de 3 mm de côté. Le confondre avec
 * `mismatch` bloquerait des prestataires honnêtes pour un défaut de numérisation — c'est un cas
 * pour l'œil d'un administrateur, pas pour un seuil.
 */
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

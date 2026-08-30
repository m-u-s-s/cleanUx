<?php

namespace App\Services\Conditions;

use Closure;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

/** La liaison d'un champ expose vers ce que SQL recevra. Trois formes, jamais melangees. */
final class FieldBinding
{
    private function __construct(
        public readonly ?string $colonne,
        public readonly ?Closure $jointure,
        public readonly bool $servable,
    ) {}

    public static function colonne(string $colonne): self
    {
        return new self($colonne, null, true);
    }

    /**
     * @param  Closure(Builder<Model>): ?string  $jointure
     *
     * La fermeture recoit la requete RACINE — jamais le noeud courant : une jointure
     * posee sur un constructeur imbrique n'est pas compilee.
     */
    public static function jointe(Closure $jointure): self
    {
        return new self(null, $jointure, true);
    }

    public static function indisponible(): self
    {
        return new self(null, null, false);
    }
}

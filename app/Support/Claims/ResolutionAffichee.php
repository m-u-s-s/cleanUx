<?php

namespace App\Support\Claims;

use Carbon\Carbon;

/** L'ISSUE D'UNE RÉCLAMATION, TELLE QUE L'ÉCRAN L'AFFICHE. */
class ResolutionAffichee
{
    public function __construct(
        public readonly string $resolution_type,
        public readonly string $explanation,
        public readonly ?Carbon $created_at = null,

        // Ce modèle-ci ne porte aucun dédommagement chiffré. La vue en affiche un sous
        // condition : les champs existent pour qu'elle ne lève pas, et restent nuls.
        public readonly ?float $amount = null,
        public readonly ?string $currency = null,
    ) {}
}

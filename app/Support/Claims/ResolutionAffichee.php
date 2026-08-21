<?php

namespace App\Support\Claims;

use Carbon\Carbon;

/**
 * L'ISSUE D'UNE RÉCLAMATION, TELLE QUE L'ÉCRAN L'AFFICHE.
 *
 * `customer_claims` porte son issue dans deux colonnes — `resolution` et `resolved_at` — là
 * où la vue parcourt une liste. On lui donne la forme qu'elle attend, sans créer de table
 * pour une ligne unique et sans emprunter celle de l'autre modèle de litige.
 *
 * Une classe plutôt qu'un objet anonyme : la forme est nommée, typée, et l'analyse statique
 * n'a plus à la deviner champ par champ.
 */
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

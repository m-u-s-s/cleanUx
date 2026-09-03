<?php

namespace App\Support\Platform;

use App\Services\Platform\SiegeDuSuperAdmin;

/**
 * LA PORTE AMONT DU SIÈGE.
 *
 * Le modèle `User` refuse toute écriture qui poserait ou déplacerait le rôle de
 * super-administrateur — seeder, `forceFill`, console, `/livewire/update` : aucun middleware ne
 * couvre ces quatre voies, un crochet de modèle si.
 *
 * Cette classe est le seul moyen d'ouvrir cette porte, et {@see SiegeDuSuperAdmin}
 * en est le seul appelant. Le drapeau est volontairement minuscule et sans état persistant : ce
 * qu'il autorise doit tenir dans une transaction, pas dans une requête entière.
 */
final class PorteDuSiege
{
    private static bool $ouverte = false;

    public static function estOuverte(): bool
    {
        return self::$ouverte;
    }

    /**
     * Ouvre la porte le temps du bloc, et la referme QUOI QU'IL ARRIVE.
     *
     * Sans le `finally`, une exception au milieu d'un transfert laisserait la porte ouverte pour
     * le reste de la requête — c'est-à-dire pour toutes les écritures qui suivent.
     *
     * @template T
     *
     * @param  callable(): T  $geste
     * @return T
     */
    public static function ouvrir(callable $geste): mixed
    {
        $anterieure = self::$ouverte;
        self::$ouverte = true;

        try {
            return $geste();
        } finally {
            self::$ouverte = $anterieure;
        }
    }
}

<?php

namespace App\Admin\Console;

use Illuminate\Database\Eloquent\Model;

/**
 * Les réponses par défaut aux deux questions que le moteur pose avant d'écrire.
 *
 * POURQUOI UN TRAIT ET NON UNE MÉTHODE DE PLUS DANS `EloquentResource`. Sur les 71 descripteurs, 61
 * étendent cette base et **10 implémentent le contrat directement** — parce que leur domaine ne se
 * décrit pas par un modèle et des colonnes. Élargir l'interface cassait ces dix-là d'un coup.
 *
 * Le trait donne la réponse neutre à qui n'a rien de particulier à dire, sans obliger chaque
 * descripteur à recopier deux méthodes vides. Ceux qui ont une règle — un pays et ses zones, une
 * zone et ses réservations — les redéfinissent.
 */
trait DefaultsResourceWrites
{
    /**
     * Les données validées partent telles quelles.
     *
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    public function prepareForCreate(array $data): array
    {
        return $data;
    }

    /**
     * Aucune action globale par défaut.
     *
     * @return list<Action>
     */
    public function globalActions(): array
    {
        return [];
    }

    /**
     * Rien ne s'oppose à la suppression.
     *
     * Le défaut permissif est assumé : la plupart des lignes de la console sont des
     * enregistrements plats que rien ne référence. Les domaines qui portent des dépendances
     * redéfinissent cette méthode, et le moteur refuse alors en disant pourquoi.
     *
     * @return list<string>
     */
    public function reasonsToRefuseDelete(Model $model): array
    {
        return [];
    }
}

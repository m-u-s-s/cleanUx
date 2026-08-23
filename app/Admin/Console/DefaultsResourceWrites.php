<?php

namespace App\Admin\Console;

use Illuminate\Database\Eloquent\Model;

/** Les réponses par défaut aux deux questions que le moteur pose avant d'écrire. */
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
     * @return list<string>
     */
    public function reasonsToRefuseDelete(Model $model): array
    {
        return [];
    }
}

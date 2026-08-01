<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\MorphTo;

/**
 * Un libellé du catalogue dans une langue donnée.
 *
 * L'absence de ligne EST l'information : elle veut dire « pas encore traduit », et l'affichage
 * retombe sur le libellé de base. Une colonne vide ne distinguerait pas cela d'une traduction
 * volontairement identique à l'original.
 */
class CatalogTranslation extends Model
{
    protected $fillable = ['translatable_type', 'translatable_id', 'locale', 'field', 'value'];

    /** @return MorphTo<Model, $this> */
    public function translatable(): MorphTo
    {
        return $this->morphTo();
    }
}

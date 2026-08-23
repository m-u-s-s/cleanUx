<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\MorphTo;

/** Un libellé du catalogue dans une langue donnée. */
class CatalogTranslation extends Model
{
    protected $fillable = ['translatable_type', 'translatable_id', 'locale', 'field', 'value'];

    /** @return MorphTo<Model, $this> */
    public function translatable(): MorphTo
    {
        return $this->morphTo();
    }
}

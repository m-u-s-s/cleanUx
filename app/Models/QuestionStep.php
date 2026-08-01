<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Regroupement optionnel de questions en etapes.
 *
 * Existe pour la loi 3 : au-dela de sept questions, on scinde en deux etapes avec une progression
 * honnete, plutot que d'empiler quinze champs sur une page.
 */
class QuestionStep extends Model
{
    protected $fillable = ['trade_id', 'title', 'subtitle', 'sort_order'];

    protected $casts = ['sort_order' => 'integer'];

    /** @return BelongsTo<Trade, $this> */
    public function trade(): BelongsTo
    {
        return $this->belongsTo(Trade::class);
    }

    /** @return HasMany<Question, $this> */
    public function questions(): HasMany
    {
        return $this->hasMany(Question::class, 'step_id')->orderBy('sort_order');
    }
}

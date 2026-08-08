<?php

namespace App\Models;

use App\Models\Concerns\HasCatalogTranslations;
use App\Models\Contracts\TranslatesCatalogLabels;
use App\Services\Audit\Concerns\AuditsEloquentEvents;
use Database\Factories\QuestionStepFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Regroupement optionnel de questions en etapes.
 *
 * Existe pour la loi 3 : au-dela de sept questions, on scinde en deux etapes avec une progression
 * honnete, plutot que d'empiler quinze champs sur une page.
 */
class QuestionStep extends Model implements TranslatesCatalogLabels
{
    use AuditsEloquentEvents, HasCatalogTranslations;

    /** @use HasFactory<QuestionStepFactory> */
    use HasFactory;

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

    /* Un changement ici déplace des prix pour de vrais clients : il doit laisser une trace. */
    protected function auditEventDomain(): string
    {
        return 'catalog';
    }
}

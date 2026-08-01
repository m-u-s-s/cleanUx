<?php

namespace App\Models;

use App\Models\Concerns\HasCatalogTranslations;
use App\Services\Audit\Concerns\AuditsEloquentEvents;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Une reponse possible, avec son impact sur le prix et sur la duree.
 *
 * `is_default` porte la loi 5 : la reponse la plus frequente est pre-selectionnee, et c'est
 * l'administrateur qui decide laquelle. Le client valide plus qu'il ne remplit.
 */
class QuestionOption extends Model
{
    use AuditsEloquentEvents, HasCatalogTranslations;

    protected $fillable = [
        'question_id', 'label', 'description', 'icon', 'value',
        'price_modifier_cents', 'price_multiplier', 'duration_modifier_min',
        'sort_order', 'is_default', 'is_active',
    ];

    protected $casts = [
        'price_modifier_cents' => 'integer',
        'price_multiplier' => 'float',
        'duration_modifier_min' => 'integer',
        'sort_order' => 'integer',
        'is_default' => 'boolean',
        'is_active' => 'boolean',
    ];

    /** @return BelongsTo<Question, $this> */
    public function question(): BelongsTo
    {
        return $this->belongsTo(Question::class);
    }

    public function scopeActive(Builder $q): Builder
    {
        return $q->where('is_active', true);
    }

    /* Un changement ici déplace des prix pour de vrais clients : il doit laisser une trace. */
    protected function auditEventDomain(): string
    {
        return 'catalog';
    }
}

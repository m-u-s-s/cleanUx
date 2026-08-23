<?php

namespace App\Models;

use App\Models\Concerns\HasCatalogTranslations;
use App\Models\Contracts\TranslatesCatalogLabels;
use App\Services\Audit\Concerns\AuditsEloquentEvents;
use App\Support\Domain\QuestionType;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * Une question du parcours de commande.
 *
 * @property-read QuestionStep|null $step
 */
class Question extends Model implements TranslatesCatalogLabels
{
    use AuditsEloquentEvents, HasCatalogTranslations, SoftDeletes;

    protected $fillable = [
        'trade_id', 'step_id', 'code', 'label', 'help_text', 'placeholder', 'type',
        // Départ ou arrivée, pour les seules questions de type `location`. C'est de ce champ que
        // se déduit « ce métier est un trajet » — cf. App\Support\Domain\TradeRouteRules.
        'location_role',
        'is_required', 'sort_order', 'default_value', 'validation', 'pricing',
        'duration_impact_min', 'display', 'allows_unknown', 'is_essential', 'is_active',
    ];

    protected $casts = [
        'is_required' => 'boolean',
        'sort_order' => 'integer',
        'default_value' => 'array',
        'validation' => 'array',
        'pricing' => 'array',
        'display' => 'array',
        'duration_impact_min' => 'integer',
        'allows_unknown' => 'boolean',
        'is_essential' => 'boolean',
        'is_active' => 'boolean',
    ];

    /** @return BelongsTo<Trade, $this> */
    public function trade(): BelongsTo
    {
        return $this->belongsTo(Trade::class);
    }

    /**
     * L'étape à laquelle la question appartient — ou AUCUNE.
     *
     * @return BelongsTo<QuestionStep, $this>
     */
    public function step(): BelongsTo
    {
        return $this->belongsTo(QuestionStep::class, 'step_id');
    }

    /** @return HasMany<QuestionOption, $this> */
    public function options(): HasMany
    {
        return $this->hasMany(QuestionOption::class)->orderBy('sort_order');
    }

    /**
     * Conditions qui décident de l'affichage de CETTE question.
     *
     * @return HasMany<QuestionCondition, $this>
     */
    public function conditions(): HasMany
    {
        return $this->hasMany(QuestionCondition::class);
    }

    public function scopeActive(Builder $q): Builder
    {
        return $q->where('is_active', true);
    }

    public function scopeOrdered(Builder $q): Builder
    {
        return $q->orderBy('sort_order')->orderBy('id');
    }

    /** Le mode immédiat ne pose que celles-ci : la vitesse prime, la fourchette est plus large. */
    public function scopeEssential(Builder $q): Builder
    {
        return $q->where('is_essential', true);
    }

    public function isNumeric(): bool
    {
        return in_array($this->type, QuestionType::numeric(), true);
    }

    public function isOptionBased(): bool
    {
        return in_array($this->type, QuestionType::optionBased(), true);
    }

    /** Un point sur la carte, avec ses coordonnées — distinct du champ texte `address`. */
    public function isLocation(): bool
    {
        return $this->type === QuestionType::LOCATION;
    }

    /** Option pré-sélectionnée, s'il y en a une. */
    public function defaultOption(): ?QuestionOption
    {
        return $this->options->firstWhere('is_default', true);
    }

    /** Bornes numériques déclarées par l'administrateur, employées quand la réponse est inconnue. */
    public function validationBounds(): array
    {
        return [
            'min' => $this->validation['min'] ?? null,
            'max' => $this->validation['max'] ?? null,
        ];
    }

    /** La bibliothèque : des modèles de questions, jamais rendus tels quels. */
    public function scopeLibrary(Builder $q): Builder
    {
        return $q->whereNull('trade_id');
    }

    public function isLibraryTemplate(): bool
    {
        return $this->trade_id === null;
    }

    // Un changement ici DÉPLACE DES PRIX pour de vrais clients.
    protected function auditEventDomain(): string
    {
        return 'catalog';
    }
}

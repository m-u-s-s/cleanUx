<?php

namespace App\Models;

use App\Models\Concerns\HasCatalogTranslations;
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
 * `code` est la clé stable, et c'est LUI qui vit dans les réponses — jamais l'identifiant
 * numérique, qui ne survit ni à un export/import entre environnements, ni à la duplication d'un
 * jeu de questions d'un métier vers un autre.
 *
 * `trade_id` nul = question globale, réutilisable entre métiers (étage, accès, fourniture du
 * matériel). C'est ce qui évite de réécrire quinze fois « Y a-t-il un ascenseur ? » et, surtout,
 * de le réécrire quinze fois différemment.
 */
class Question extends Model
{
    use AuditsEloquentEvents, HasCatalogTranslations, SoftDeletes;

    protected $fillable = [
        'trade_id', 'step_id', 'code', 'label', 'help_text', 'placeholder', 'type',
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

    /** @return BelongsTo<QuestionStep, $this> */
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

    /**
     * Option pré-sélectionnée, s'il y en a une.
     *
     * Le défaut n'est pas une commodité de développeur : c'est la loi 5. Sans lui, le client
     * remplit ; avec lui, il valide.
     */
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

    /**
     * La bibliothèque : des modèles de questions, jamais rendus tels quels.
     *
     * Une question de bibliothèque se COPIE dans un métier ; elle n'apparaît pas dans un parcours.
     * Les questions restent au niveau du métier — un libellé partagé en direct entre douze métiers
     * ferait qu'un ajustement de prix pour la peinture déplacerait aussi celui de la plomberie.
     */
    public function scopeLibrary(Builder $q): Builder
    {
        return $q->whereNull('trade_id');
    }

    public function isLibraryTemplate(): bool
    {
        return $this->trade_id === null;
    }

    /*
     * Un changement ici DÉPLACE DES PRIX pour de vrais clients. L'audit n'est pas une formalité :
     * c'est le seul moyen de répondre à « pourquoi cette prestation coûtait 40 € hier et 60 € ce
     * matin » autrement qu'en devinant.
     */
    protected function auditEventDomain(): string
    {
        return 'catalog';
    }
}

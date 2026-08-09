<?php

namespace App\Models;

use App\Services\Audit\Concerns\AuditsEloquentEvents;
use App\Support\Domain\OrderMode;
use Database\Factories\TradeFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * Trade — corps de métier (Nettoyage, Bâtiment, Peinture, Levage…).
 *
 * Regroupe les ServiceCatalog en familles cohérentes côté UI marketplace
 * et côté matching prestataire (skills_required, certifications…).
 *
 * @property int $id
 * @property string $slug
 * @property string $code
 * @property string $name
 * @property bool $is_active
 * @property int $sort_order
 */
class Trade extends Model
{
    use AuditsEloquentEvents;

    /** @use HasFactory<TradeFactory> */
    use HasFactory;

    use SoftDeletes;

    protected $fillable = [
        'slug',
        'code',
        'name',
        'icon',
        'color',
        'cover_image_path',
        'short_description',
        'description',
        'is_active',
        'requires_certification',
        'requires_insurance_proof',
        'is_b2b_default',
        'is_personal_default',
        'sort_order',
        'settings',
        'metadata',
        // Chantier A — propriétés métier exploitées par le pricing/workflow
        'default_hourly_rate',
        'emergency_multiplier',
        'night_multiplier',
        'weekend_multiplier',
        'quote_validity_days',
        'requires_quote_by_default',
        'sla_response_minutes',
        // Phase F1 — schema dynamique de formulaire de réservation
        'booking_form_schema',
        // Questions posées au PRESTATAIRE qui déclare exercer ce métier — distinctes de
        // booking_form_schema, qui décrit celles posées au CLIENT qui réserve.
        'provider_form_schema',
        // Multi-trade spec columns (migration 2026_05_27_000000)
        'billing_unit',
        'requires_site_visit',
        // Moteur de commande — le métier devient pilotable depuis le constructeur de parcours.
        // Colonnes ajoutées, jamais une seconde table de métiers : deux vérités pour la même
        // chose est exactement ce qui a valu la suppression de `tenancy_v2` sur ce projet.
        'sector_id',
        'base_price_cents',
        'pricing_unit',
        'estimated_duration_min',
        'min_duration_min',
        'allows_scheduled',
        'allows_asap',
        'allows_bundle',
        'published_at',
    ];

    protected $casts = [
        'is_active' => 'boolean',
        'requires_certification' => 'boolean',
        'requires_insurance_proof' => 'boolean',
        'is_b2b_default' => 'boolean',
        'is_personal_default' => 'boolean',
        'sort_order' => 'integer',
        'settings' => 'array',
        'metadata' => 'array',
        'default_hourly_rate' => 'decimal:2',
        'emergency_multiplier' => 'decimal:2',
        'night_multiplier' => 'decimal:2',
        'weekend_multiplier' => 'decimal:2',
        'quote_validity_days' => 'integer',
        'requires_quote_by_default' => 'boolean',
        'sla_response_minutes' => 'integer',
        'booking_form_schema' => 'array',
        'provider_form_schema' => 'array',
        'requires_site_visit' => 'boolean',
        'base_price_cents' => 'integer',
        'estimated_duration_min' => 'integer',
        'min_duration_min' => 'integer',
        'allows_scheduled' => 'boolean',
        'allows_asap' => 'boolean',
        'allows_bundle' => 'boolean',
        'published_at' => 'datetime',
    ];

    /**
     * Le mode est-il ouvert sur ce métier ?
     *
     * Tous les métiers ne se prêtent pas au service immédiat : un ravalement de façade n'est pas
     * un service Uber. Le défaut de `allows_asap` est donc faux — l'ouvrir est une décision
     * d'administrateur, jamais un oubli.
     */
    public function allowsMode(string $mode): bool
    {
        return (bool) $this->getAttribute(OrderMode::tradeFlag($mode));
    }

    // ──────────────────────────────────────────────────────
    // Relations
    // ──────────────────────────────────────────────────────

    /** @return BelongsTo<Sector, $this> */
    public function sector(): BelongsTo
    {
        return $this->belongsTo(Sector::class);
    }

    /**
     * Questions du parcours de commande, propres a ce metier.
     *
     * Les questions GLOBALES (trade_id nul) ne sont pas ici : elles sont assemblees par le
     * constructeur de questionnaire, qui decide lesquelles s'appliquent.
     *
     * @return HasMany<Question, $this>
     */
    public function questions(): HasMany
    {
        return $this->hasMany(Question::class)->orderBy('sort_order');
    }

    /** @return HasMany<QuestionStep, $this> */
    public function questionSteps(): HasMany
    {
        return $this->hasMany(QuestionStep::class)->orderBy('sort_order');
    }

    /** @return HasMany<TradeFormRevision, $this> */
    public function formRevisions(): HasMany
    {
        return $this->hasMany(TradeFormRevision::class)->orderByDesc('version');
    }

    /** @return HasMany<TradeBundleSuggestion, $this> */
    public function bundleSuggestions(): HasMany
    {
        return $this->hasMany(TradeBundleSuggestion::class)->orderBy('sort_order');
    }

    /** @return HasMany<ServiceCatalog, $this> */
    public function services(): HasMany
    {
        return $this->hasMany(ServiceCatalog::class)
            ->orderBy('sort_order')
            ->orderBy('name');
    }

    /** @return HasMany<ServiceCatalog, $this> */
    public function activeServices(): HasMany
    {
        return $this->services()->where('is_active', true);
    }

    /**
     * Alias historique de `zonePricing()` : les deux nommaient deux tables, ils nomment desormais
     * la meme ligne (metier, zone).
     *
     * @return HasMany<TradeZonePricing, $this>
     */
    public function zoneSettings(): HasMany
    {
        return $this->hasMany(TradeZonePricing::class);
    }

    /** @return HasMany<TradeZonePricing, $this> */
    public function zonePricing(): HasMany
    {
        return $this->hasMany(TradeZonePricing::class);
    }

    /** @return HasMany<ProviderTradeCertification, $this> */
    public function certifications(): HasMany
    {
        return $this->hasMany(ProviderTradeCertification::class);
    }

    /** @return BelongsToMany<ServiceZone, $this> */
    public function zones(): BelongsToMany
    {
        return $this->belongsToMany(ServiceZone::class, 'trade_zone_pricing')
            ->withPivot(['is_active', 'surge_multiplier', 'base_rate_cents', 'asap_enabled'])
            ->withTimestamps();
    }

    /**
     * Utilisateurs (employés ou prestataires) habilités à exécuter ce métier.
     *
     * @return BelongsToMany<User, $this>
     */
    public function users(): BelongsToMany
    {
        return $this->belongsToMany(User::class, 'trade_user')
            ->withPivot(['is_primary', 'proficiency', 'notes'])
            ->withTimestamps();
    }

    /**
     * Alias filtré sur les utilisateurs ayant le rôle employé ou prestataire.
     */
    public function employees(): BelongsToMany
    {
        return $this->users()
            ->whereIn('role', [User::ROLE_EMPLOYE, User::ROLE_PROVIDER]);
    }

    // ──────────────────────────────────────────────────────
    // Scopes
    // ──────────────────────────────────────────────────────

    public function scopeActive(Builder $q): Builder
    {
        return $q->where('is_active', true);
    }

    public function scopeOrdered(Builder $q): Builder
    {
        return $q->orderBy('sort_order')->orderBy('name');
    }

    // ──────────────────────────────────────────────────────
    // Accessors
    // ──────────────────────────────────────────────────────

    public function getDisplayNameAttribute(): string
    {
        return (string) ($this->name ?: $this->code);
    }

    public function getDefaultIconAttribute(): string
    {
        return $this->icon ?: 'briefcase';
    }

    /*
     * Le métier porte le prix plancher et les trois interrupteurs de mode.
     *
     * Ouvrir `allows_asap` sur un ravalement de façade engage la plateforme à dépêcher quelqu'un
     * dans l'heure sur un chantier qui dure trois jours. C'est un geste d'administration ordinaire
     * à l'écran, et lourd de conséquences : il doit être attribuable.
     */
    protected function auditEventDomain(): string
    {
        return 'catalog';
    }
}

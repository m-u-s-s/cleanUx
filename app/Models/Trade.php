<?php

namespace App\Models;

use App\Models\Pivots\TradeUser;
use App\Services\Audit\Concerns\AuditsEloquentEvents;
use App\Support\Domain\OrderMode;
use App\Support\Domain\TradeRouteRules;
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
 *
 * La ligne de rattachement `trade_user`, présente UNIQUEMENT quand le métier a été chargé depuis un
 * prestataire (`$user->trades`). Elle porte `is_primary`, `proficiency` et `notes` — le niveau
 * déclaré sur CE métier, qui n'a de sens que pour cette personne-là.
 * @property-read TradeUser|null $pivot
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
        'requires_face_check',
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
        /*
         * Règles du transport de personnes — véhicule récent, carte grise, assurance. Décision
         * d'exploitation, indépendante du fait que le parcours décrive un trajet : une dépanneuse
         * fait un trajet sans obéir aux règles taxi.
         */
        'taxi_rules',
        // Quand chaque exigence est née. La période de grâce laissée aux prestataires déjà
        // inscrits part de ces dates, jamais de « maintenant ».
        'route_rules_since',
        'taxi_rules_since',
    ];

    protected $casts = [
        'is_active' => 'boolean',
        'requires_certification' => 'boolean',
        'requires_insurance_proof' => 'boolean',
        'requires_face_check' => 'boolean',
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
        'taxi_rules' => 'boolean',
        'route_rules_since' => 'datetime',
        'taxi_rules_since' => 'datetime',
    ];

    /**
     * RESTREINDRE UN CATALOGUE AU MODE DEMANDÉ — « l'immédiat ne contient que l'immédiat ».
     *
     * DEUX VERROUS, ET ILS NE DISENT PAS LA MÊME CHOSE. `allows_asap` dit qu'un métier PEUT se
     * faire dans l'heure — un ravalement de façade ne le peut nulle part. La ligne `(métier, zone)`
     * de `trade_zone_pricing` dit qu'on l'a ouvert ICI : c'est la décision d'exploitation, et
     * promettre un dépannage dans une zone où personne n'est jamais en ligne fait attendre le
     * client pour rien.
     *
     * LE SECOND VERROU N'AGIT QU'UNE FOIS L'ADRESSE CONNUE. Avant, la zone est inconnue : filtrer
     * dessus viderait l'écran pour tout le monde, y compris là où l'immédiat est ouvert.
     *
     * SANS MODE DEMANDÉ, RIEN N'EST RETIRÉ — le client qui entre par le catalogue voit tout, et
     * choisit son mode métier par métier. C'est le parcours historique, et il reste juste.
     *
     * La règle vit ICI et non dans l'écran qui l'emploie : le parcours de commande, le carrousel de
     * secteurs et le décompte par secteur posent la même question, et trois copies auraient fini
     * par répondre différemment.
     *
     * @param  Builder<Trade>  $query
     */
    public function scopeServableEnMode(Builder $query, ?string $mode, ?int $zoneId = null): void
    {
        if ($mode === null || $mode === OrderMode::SCHEDULED) {
            return;
        }

        $query->where(OrderMode::tradeFlag($mode), true);

        if ($mode === OrderMode::ASAP && $zoneId !== null) {
            $query->whereExists(function ($sous) use ($zoneId) {
                $sous->selectRaw('1')
                    ->from('trade_zone_pricing')
                    ->whereColumn('trade_zone_pricing.trade_id', 'trades.id')
                    ->where('trade_zone_pricing.service_zone_id', $zoneId)
                    ->where('trade_zone_pricing.is_active', true)
                    ->where('trade_zone_pricing.asap_enabled', true);
            });
        }
    }

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

    /**
     * Ce métier emmène-t-il quelqu'un d'un point à un autre ?
     *
     * La réponse se dérive du PARCOURS — deux questions de localisation, un départ et une arrivée —
     * et jamais d'un drapeau posé à côté. La règle vit dans {@see TradeRouteRules} et n'est pas
     * recopiée ici : une règle d'identité qui existe en deux exemplaires finit toujours par diverger.
     */
    public function estUnTrajet(): bool
    {
        return TradeRouteRules::estUnTrajet($this);
    }

    /**
     * Les métiers dont le parcours décrit un trajet.
     *
     * @param  Builder<Trade>  $query
     */
    public function scopeTrajet(Builder $query): void
    {
        TradeRouteRules::scopeTrajet($query);
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

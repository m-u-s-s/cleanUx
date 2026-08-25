<?php

namespace App\Models;

use Database\Factories\ServiceZoneFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

class ServiceZone extends Model
{
    /** @use HasFactory<ServiceZoneFactory> */
    use HasFactory;

    protected $fillable = [
        'country_id',
        'region_id',
        'province_id',
        'commune_id',
        'parent_zone_id',
        'code',
        'name',
        'slug',
        'coverage_type',
        'status',
        'is_bookable',
        'is_visible',
        'priority',
        'minimum_notice_hours',
        'maximum_daily_jobs',
        'travel_surcharge',
        'time_buffer_minutes',
        'metadata',
        'notes',
        'activated_at',
        'deactivated_at',
    ];

    protected $casts = [
        'is_bookable' => 'boolean',
        'is_visible' => 'boolean',
        'travel_surcharge' => 'decimal:2',
        'metadata' => 'array',
        'activated_at' => 'datetime',
        'deactivated_at' => 'datetime',
    ];

    /** @return BelongsTo<Country, $this> */
    public function country(): BelongsTo
    {
        return $this->belongsTo(Country::class);
    }

    /**
     * LA DEVISE D'UN TARIF DE ZONE SUIT LA ZONE, PAS CELUI QUI LA REGARDE.
     *
     * `Money::deviseDuContexte()` rend la devise du LECTEUR : juste pour une facture qu'on
     * lui adresse, faux pour un bareme qu'il configure ailleurs. Un administrateur belge
     * tarifant Casablanca lisait des euros la ou le client paiera des dirhams.
     */
    public function deviseDeLaZone(): string
    {
        $pays = $this->relationLoaded('country') ? $this->country : $this->country()->first();

        $posee = $pays?->currency_code;

        if (is_string($posee) && trim($posee) !== '') {
            return strtoupper(trim($posee));
        }

        // `countries.currency_code` est NOT NULL : le seul chemin qui arrive ici est une zone
        // SANS pays — `country_id` l'autorise. Aucun repli par position, il serait mort.
        return strtoupper((string) config('fx.base_currency', 'EUR'));
    }

    /** @return BelongsTo<Region, $this> */
    public function region(): BelongsTo
    {
        return $this->belongsTo(Region::class);
    }

    /** @return BelongsTo<Province, $this> */
    public function province(): BelongsTo
    {
        return $this->belongsTo(Province::class);
    }

    /** @return BelongsTo<Commune, $this> */
    public function commune(): BelongsTo
    {
        return $this->belongsTo(Commune::class);
    }

    /** @return BelongsTo<self, $this> */
    public function parentZone(): BelongsTo
    {
        return $this->belongsTo(self::class, 'parent_zone_id');
    }

    /** @return HasMany<self, $this> */
    public function childZones(): HasMany
    {
        return $this->hasMany(self::class, 'parent_zone_id');
    }

    /** @return BelongsToMany<PostalCode, $this> */
    public function postalCodes(): BelongsToMany
    {
        return $this->belongsToMany(PostalCode::class, 'service_zone_postal_code')
            ->withPivot('is_primary')
            ->withTimestamps();
    }

    /** @return HasMany<ZoneServiceRule, $this> */
    public function zoneServiceRules(): HasMany
    {
        return $this->hasMany(ZoneServiceRule::class);
    }

    /** @return BelongsToMany<ServiceCatalog, $this> */
    public function serviceCatalogs(): BelongsToMany
    {
        return $this->belongsToMany(ServiceCatalog::class, 'zone_service_rules')
            ->withPivot([
                'is_enabled',
                'requires_manual_validation',
                'base_price_override',
                'price_multiplier',
                'minimum_notice_hours',
                'maximum_daily_capacity',
                'settings',
            ])
            ->withTimestamps();
    }

    /** @return HasMany<EmployeeZoneAssignment, $this> */
    public function employeeAssignments(): HasMany
    {
        return $this->hasMany(EmployeeZoneAssignment::class);
    }

    /** @return BelongsToMany<User, $this> */
    public function employees(): BelongsToMany
    {
        return $this->belongsToMany(User::class, 'employee_zone_assignments')
            ->withPivot(['assignment_type', 'coverage_priority', 'is_active', 'starts_at', 'ends_at', 'notes'])
            ->withTimestamps();
    }

    /** @return HasMany<OrganizationSite, $this> */
    public function organizationSites(): HasMany
    {
        return $this->hasMany(OrganizationSite::class);
    }

    /** @return HasMany<FieldTeam, $this> */
    public function fieldTeams(): HasMany
    {
        return $this->hasMany(FieldTeam::class);
    }

    /** @return HasMany<PartnerZoneCoverage, $this> */
    public function partnerZoneCoverages(): HasMany
    {
        return $this->hasMany(PartnerZoneCoverage::class);
    }

    /** @return BelongsToMany<ServicePartner, $this> */
    public function servicePartners(): BelongsToMany
    {
        return $this->belongsToMany(ServicePartner::class, 'partner_zone_coverages')
            ->withPivot(['service_catalog_id', 'coverage_status', 'priority', 'max_daily_capacity', 'sla_response_hours', 'metadata'])
            ->withTimestamps();
    }

    /** @return HasMany<Booking, $this> */
    public function rendezVous(): HasMany
    {
        return $this->hasMany(Booking::class);
    }

    /**
     * Les lignes (metier, zone) de cette zone : activation ET prix, sur la meme ligne.
     *
     * @return HasMany<TradeZonePricing, $this>
     */
    public function tradeSettings(): HasMany
    {
        return $this->hasMany(TradeZonePricing::class);
    }

    /**
     * Les metiers vendus dans cette zone.
     *
     * @return BelongsToMany<Trade, $this>
     */
    public function trades(): BelongsToMany
    {
        return $this->belongsToMany(Trade::class, 'trade_zone_pricing')
            ->withPivot(['is_active', 'surge_multiplier', 'base_rate_cents', 'asap_enabled'])
            ->withTimestamps();
    }
}

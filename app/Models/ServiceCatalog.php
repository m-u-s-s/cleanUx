<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * ServiceCatalog — catalogue de services proposés par la plateforme.
 *
 * PHASE 1 multi-métiers : un service appartient désormais à un Trade
 * (corps de métier). Voir migration extend_service_catalogs_with_trade_and_v3_columns.
 *
 * Champs ajoutés en Phase 1 :
 *   trade_id, billing_unit, vat_rate, min_lead_time_hours, requires_site_visit,
 *   icon, color, cover_image_path, tags, skills_required, is_featured
 */
class ServiceCatalog extends Model
{
    use HasFactory;

    protected $fillable = [
        // Existant V1/V2
        'code',
        'name',
        'slug',
        'description',
        'service_type',
        'category',
        'is_active',
        'requires_quote',
        'requires_manual_validation',
        'is_entreprise',
        'is_b2b_available',
        'is_personal_available',
        'default_duration_minutes',
        'base_price',
        'currency',
        'sort_order',
        'settings',
        'options', // JSON legacy — conservé pour back-compat
        'metadata',

        // Phase 1 multi-métiers
        'trade_id',
        'billing_unit',
        'vat_rate',
        'min_lead_time_hours',
        'requires_site_visit',
        'icon',
        'color',
        'cover_image_path',
        'tags',
        'skills_required',
        'is_featured',
    ];

    protected $casts = [
        // Existant
        'is_active'                  => 'boolean',
        'requires_quote'             => 'boolean',
        'requires_manual_validation' => 'boolean',
        'is_entreprise'              => 'boolean',
        'is_b2b_available'           => 'boolean',
        'is_personal_available'      => 'boolean',
        'base_price'                 => 'decimal:2',
        'settings'                   => 'array',
        'options'                    => 'array',
        'metadata'                   => 'array',

        // Phase 1
        'vat_rate'             => 'decimal:2',
        'min_lead_time_hours'  => 'integer',
        'requires_site_visit'  => 'boolean',
        'tags'                 => 'array',
        'skills_required'      => 'array',
        'is_featured'          => 'boolean',
    ];

    // ──────────────────────────────────────────────────────
    // Constantes
    // ──────────────────────────────────────────────────────

    public const BILLING_UNITS = ['hour', 'sqm', 'flat', 'quote'];

    public function getDisplayNameAttribute(): string
    {
        $name = (string) ($this->name ?: $this->code ?: $this->service_type ?: 'Service');
        return (string) str($name)->replace('_', ' ')->headline();
    }

    // ──────────────────────────────────────────────────────
    // Relations existantes (conservées telles quelles)
    // ──────────────────────────────────────────────────────

    public function zoneServiceRules(): HasMany
    {
        return $this->hasMany(ZoneServiceRule::class);
    }

    public function serviceZones(): BelongsToMany
    {
        return $this->belongsToMany(ServiceZone::class, 'zone_service_rules')
            ->withPivot([
                'is_enabled', 'requires_manual_validation', 'base_price_override',
                'price_multiplier', 'minimum_notice_hours', 'maximum_daily_capacity', 'settings',
            ])
            ->withTimestamps();
    }

    public function rendezVous(): HasMany
    {
        return $this->hasMany(RendezVous::class);
    }

    public function countryServiceCatalogRules(): HasMany
    {
        return $this->hasMany(CountryServiceCatalogRule::class);
    }

    // ──────────────────────────────────────────────────────
    // Phase 1 — relations
    // ──────────────────────────────────────────────────────

    public function trade(): BelongsTo
    {
        return $this->belongsTo(Trade::class);
    }

    public function options(): HasMany
    {
        return $this->hasMany(ServiceOption::class)
            ->orderBy('sort_order')
            ->orderBy('id');
    }

    public function activeOptions(): HasMany
    {
        return $this->options()->where('is_active', true);
    }

    // ──────────────────────────────────────────────────────
    // Phase 1 — scopes
    // ──────────────────────────────────────────────────────

    public function scopeActive(Builder $q): Builder
    {
        return $q->where('is_active', true);
    }

    public function scopeForTrade(Builder $q, int|Trade $trade): Builder
    {
        $tradeId = $trade instanceof Trade ? $trade->id : $trade;
        return $q->where('trade_id', $tradeId);
    }

    public function scopeFeatured(Builder $q): Builder
    {
        return $q->where('is_featured', true);
    }

    public function scopeOrdered(Builder $q): Builder
    {
        return $q->orderBy('sort_order')->orderBy('name');
    }
}

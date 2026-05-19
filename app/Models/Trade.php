<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * Trade — corps de métier (Nettoyage, Bâtiment, Peinture, Levage…).
 *
 * Regroupe les ServiceCatalog en familles cohérentes côté UI marketplace
 * et côté matching prestataire (skills_required, certifications…).
 *
 * @property int    $id
 * @property string $slug
 * @property string $code
 * @property string $name
 * @property bool   $is_active
 * @property int    $sort_order
 */
class Trade extends Model
{
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
    ];

    protected $casts = [
        'is_active'                 => 'boolean',
        'requires_certification'    => 'boolean',
        'requires_insurance_proof'  => 'boolean',
        'is_b2b_default'            => 'boolean',
        'is_personal_default'       => 'boolean',
        'sort_order'                => 'integer',
        'settings'                  => 'array',
        'metadata'                  => 'array',
        'default_hourly_rate'       => 'decimal:2',
        'emergency_multiplier'      => 'decimal:2',
        'night_multiplier'          => 'decimal:2',
        'weekend_multiplier'        => 'decimal:2',
        'quote_validity_days'       => 'integer',
        'requires_quote_by_default' => 'boolean',
        'sla_response_minutes'      => 'integer',
        'booking_form_schema'       => 'array',
    ];

    // ──────────────────────────────────────────────────────
    // Relations
    // ──────────────────────────────────────────────────────

    public function services(): HasMany
    {
        return $this->hasMany(ServiceCatalog::class)
            ->orderBy('sort_order')
            ->orderBy('name');
    }

    public function activeServices(): HasMany
    {
        return $this->services()->where('is_active', true);
    }

    public function zoneSettings(): HasMany
    {
        return $this->hasMany(TradeZoneSetting::class);
    }

    public function zones(): BelongsToMany
    {
        return $this->belongsToMany(ServiceZone::class, 'trade_zone_settings')
            ->withPivot(['is_active', 'price_multiplier', 'notes'])
            ->withTimestamps();
    }

    /**
     * Utilisateurs (employés ou prestataires) habilités à exécuter ce métier.
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
}

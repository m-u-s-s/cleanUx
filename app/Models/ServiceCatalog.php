<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

class ServiceCatalog extends Model
{
    use HasFactory;

    protected $fillable = [
        'code', 'name', 'slug', 'description', 'service_type', 'is_active', 'requires_quote',
        'requires_manual_validation', 'is_entreprise', 'default_duration_minutes', 'base_price', 'sort_order', 'settings',
    ];

    protected $casts = [
        'is_active' => 'boolean',
        'requires_quote' => 'boolean',
        'requires_manual_validation' => 'boolean',
        'is_entreprise' => 'boolean',
        'base_price' => 'decimal:2',
        'settings' => 'array',
    ];

    public function getDisplayNameAttribute(): string
    {
        $name = (string) ($this->name ?: $this->code ?: $this->service_type ?: 'Service');
        return (string) str($name)->replace('_', ' ')->headline();
    }

    public function zoneServiceRules(): HasMany { return $this->hasMany(ZoneServiceRule::class); }
    public function serviceZones(): BelongsToMany
    {
        return $this->belongsToMany(ServiceZone::class, 'zone_service_rules')
            ->withPivot(['is_enabled','requires_manual_validation','base_price_override','price_multiplier','minimum_notice_hours','maximum_daily_capacity','settings'])
            ->withTimestamps();
    }
    public function rendezVous(): HasMany { return $this->hasMany(RendezVous::class); }
    public function countryServiceCatalogRules(): HasMany { return $this->hasMany(CountryServiceCatalogRule::class); }
}

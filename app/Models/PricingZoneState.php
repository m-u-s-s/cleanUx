<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** Phase 14 — État surge actuel d'une zone géographique. Une row par service_zone_id (unique). */
class PricingZoneState extends Model
{
    use HasFactory;

    protected $table = 'pricing_zones_state';

    protected $fillable = [
        'service_zone_id',
        'multiplier',
        'demand_factor',
        'supply_factor',
        'temporal_factor',
        'open_bookings_count',
        'online_providers_count',
        'expires_at',
        'metadata',
    ];

    protected $casts = [
        'multiplier' => 'decimal:2',
        'demand_factor' => 'decimal:2',
        'supply_factor' => 'decimal:2',
        'temporal_factor' => 'decimal:2',
        'open_bookings_count' => 'integer',
        'online_providers_count' => 'integer',
        'expires_at' => 'datetime',
        'metadata' => 'array',
    ];

    /** @return BelongsTo<ServiceZone, $this> */
    public function serviceZone(): BelongsTo
    {
        return $this->belongsTo(ServiceZone::class);
    }

    public function isActive(): bool
    {
        return $this->expires_at !== null
            && $this->expires_at->isFuture()
            && (float) $this->multiplier > 1.0;
    }

    /** Multiplier "effectif" (1.0 si expiré). */
    public function effectiveMultiplier(): float
    {
        return $this->isActive() ? (float) $this->multiplier : 1.0;
    }
}

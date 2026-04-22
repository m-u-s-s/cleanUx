<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ZoneServiceRule extends Model
{
    use HasFactory;

    protected $fillable = [
        'service_zone_id',
        'service_catalog_id',
        'is_enabled',
        'requires_manual_validation',
        'base_price_override',
        'price_multiplier',
        'minimum_notice_hours',
        'maximum_daily_capacity',
        'settings',
    ];

    protected $casts = [
        'is_enabled' => 'boolean',
        'requires_manual_validation' => 'boolean',
        'base_price_override' => 'decimal:2',
        'price_multiplier' => 'decimal:2',
        'settings' => 'array',
    ];

    public function serviceZone(): BelongsTo
    {
        return $this->belongsTo(ServiceZone::class);
    }

    public function serviceCatalog(): BelongsTo
    {
        return $this->belongsTo(ServiceCatalog::class);
    }
}

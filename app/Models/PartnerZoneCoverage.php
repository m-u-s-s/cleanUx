<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PartnerZoneCoverage extends Model
{
    use HasFactory;

    protected $fillable = [
        'service_partner_id',
        'service_zone_id',
        'service_catalog_id',
        'coverage_status',
        'priority',
        'max_daily_capacity',
        'sla_response_hours',
        'metadata',
    ];

    protected $casts = [
        'priority' => 'integer',
        'max_daily_capacity' => 'integer',
        'sla_response_hours' => 'integer',
        'metadata' => 'array',
    ];

    public function servicePartner(): BelongsTo
    {
        return $this->belongsTo(ServicePartner::class);
    }

    public function serviceZone(): BelongsTo
    {
        return $this->belongsTo(ServiceZone::class);
    }

    public function serviceCatalog(): BelongsTo
    {
        return $this->belongsTo(ServiceCatalog::class);
    }
}

<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class CountryServiceCatalogRule extends Model
{
    use HasFactory;

    protected $fillable = [
        'country_id',
        'service_catalog_id',
        'is_enabled',
        'requires_manual_validation',
        'requires_quote',
        'minimum_notice_hours',
        'sla_response_hours',
        'sla_resolution_hours',
        'default_team_id',
        'default_partner_id',
        'pricing_multiplier',
        'settings',
    ];

    protected $casts = [
        'is_enabled' => 'boolean',
        'requires_manual_validation' => 'boolean',
        'requires_quote' => 'boolean',
        'minimum_notice_hours' => 'integer',
        'sla_response_hours' => 'integer',
        'sla_resolution_hours' => 'integer',
        'default_team_id' => 'integer',
        'default_partner_id' => 'integer',
        'pricing_multiplier' => 'decimal:2',
        'settings' => 'array',
    ];

    public function country(): BelongsTo
    {
        return $this->belongsTo(Country::class);
    }

    public function serviceCatalog(): BelongsTo
    {
        return $this->belongsTo(ServiceCatalog::class);
    }
}

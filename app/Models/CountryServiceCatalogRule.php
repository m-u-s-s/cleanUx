<?php

namespace App\Models;

use Database\Factories\CountryServiceCatalogRuleFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @property bool $is_enabled
 * @property bool $requires_manual_validation
 * @property bool $requires_quote
 * @property ?int $minimum_notice_hours
 * @property ?int $sla_response_hours
 * @property ?int $sla_resolution_hours
 * @property ?int $default_team_id
 * @property ?int $default_partner_id
 * @property string $pricing_multiplier
 * @property ?array $settings
 */
class CountryServiceCatalogRule extends Model
{
    /** @use HasFactory<CountryServiceCatalogRuleFactory> */
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

        // Écrite par le code, écartée par Eloquent faute de figurer ici.
        'price_multiplier',
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

    /** @return BelongsTo<Country, $this> */
    public function country(): BelongsTo
    {
        return $this->belongsTo(Country::class);
    }

    /** @return BelongsTo<ServiceCatalog, $this> */
    public function serviceCatalog(): BelongsTo
    {
        return $this->belongsTo(ServiceCatalog::class);
    }
}

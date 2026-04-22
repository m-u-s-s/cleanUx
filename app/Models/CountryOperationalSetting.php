<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class CountryOperationalSetting extends Model
{
    use HasFactory;

    protected $fillable = [
        'country_id',
        'booking_enabled',
        'mission_enabled',
        'billing_enabled',
        'partner_network_enabled',
        'readiness_stage',
        'default_tax_rate',
        'currency_symbol',
        'date_format',
        'time_format',
        'address_format',
        'phone_format',
        'requires_vat_number_for_companies',
        'default_distance_unit',
        'default_surface_unit',
        'local_rules',
        'metadata',
    ];

    protected $casts = [
        'booking_enabled' => 'boolean',
        'mission_enabled' => 'boolean',
        'billing_enabled' => 'boolean',
        'partner_network_enabled' => 'boolean',
        'requires_vat_number_for_companies' => 'boolean',
        'default_tax_rate' => 'decimal:2',
        'local_rules' => 'array',
        'metadata' => 'array',
    ];

    public function country(): BelongsTo
    {
        return $this->belongsTo(Country::class);
    }

    public function getLaunchStageLabelAttribute(): string
    {
        return match ($this->readiness_stage) {
            'catalog_only' => 'Catalogue uniquement',
            'booking_enabled' => 'Réservation active',
            'mission_enabled' => 'Mission active',
            'billing_enabled' => 'Facturation active',
            'ready_for_launch' => 'Prêt au lancement',
            default => 'Brouillon',
        };
    }
}

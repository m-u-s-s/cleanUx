<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * ServiceOption — variante paramétrable d'un ServiceCatalog.
 *
 * Permet à un service comme "Nettoyage bureaux" d'exposer au client :
 *   - "Surface (m²)"  type=number, unit=m², price_modifier=per_unit
 *   - "Fréquence"     type=select, values=[unique,hebdo,mensuel]
 *   - "Vitres extérieures"   type=boolean
 *
 * Le calcul de prix final se fait via:
 *   ServiceCatalog::computePriceFromOptions($payload)
 *   (à implémenter en Phase 1bis si besoin métier)
 */
class ServiceOption extends Model
{
    use HasFactory;

    protected $fillable = [
        'service_catalog_id',
        'slug',
        'label',
        'help_text',
        'type',
        'values',
        'unit',
        'default_value_num',
        'default_value_str',
        'is_required',
        'price_modifier',
        'price_modifier_value',
        'min_value',
        'max_value',
        'step',
        'sort_order',
        'is_active',
    ];

    protected $casts = [
        'values'               => 'array',
        'is_required'          => 'boolean',
        'is_active'            => 'boolean',
        'default_value_num'    => 'decimal:2',
        'price_modifier_value' => 'decimal:4',
        'min_value'            => 'decimal:2',
        'max_value'            => 'decimal:2',
        'step'                 => 'decimal:4',
        'sort_order'           => 'integer',
    ];

    public function service(): BelongsTo
    {
        return $this->belongsTo(ServiceCatalog::class, 'service_catalog_id');
    }

    /** Validation list: [number, boolean, select, multiselect, text] */
    public const TYPES = ['number', 'boolean', 'select', 'multiselect', 'text'];

    /** Validation list pour price_modifier */
    public const PRICE_MODIFIERS = ['none', 'fixed', 'percent', 'per_unit'];
}

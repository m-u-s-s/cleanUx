<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class CountryBillingProfile extends Model
{
    use HasFactory;

    protected $fillable = [
        'country_id',
        'currency_code',
        'currency_symbol',
        'currency_position',
        'invoice_prefix',
        'quote_prefix',
        'tax_label',
        'default_tax_rate',
        'prices_include_tax',
        'rounding_mode',
        'decimal_separator',
        'thousands_separator',
        'payment_terms_days',
        'quote_validity_days',
        'date_format',
        'time_format',
        'requires_vat_number',
        'supports_invoicing',
        'supports_credit_notes',
        'invoice_settings',
        'payment_settings',
        'metadata',
    ];

    protected $casts = [
        'default_tax_rate' => 'decimal:2',
        'prices_include_tax' => 'boolean',
        'payment_terms_days' => 'integer',
        'metadata' => 'array',
    ];

    public function country(): BelongsTo
    {
        return $this->belongsTo(Country::class);
    }
}

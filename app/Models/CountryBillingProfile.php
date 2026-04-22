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
        'invoice_prefix',
        'quote_prefix',
        'tax_label',
        'default_tax_rate',
        'prices_include_tax',
        'rounding_mode',
        'decimal_separator',
        'thousands_separator',
        'payment_terms_days',
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

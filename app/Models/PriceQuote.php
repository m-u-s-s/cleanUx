<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PriceQuote extends Model
{
    protected $fillable = [
        'service_code', 'trade_code',
        'base_price_cents', 'computed_price_cents', 'currency',
        'variables_snapshot', 'applied_rules', 'variant_label',
        'user_id', 'booking_id',
        'idempotency_key', 'quoted_at', 'metadata',
    ];

    protected $casts = [
        'base_price_cents' => 'integer',
        'computed_price_cents' => 'integer',
        'variables_snapshot' => 'array',
        'applied_rules' => 'array',
        'quoted_at' => 'datetime',
        'metadata' => 'array',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}

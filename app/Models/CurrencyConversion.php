<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class CurrencyConversion extends Model
{
    protected $fillable = [
        'source_amount_cents', 'source_currency',
        'target_amount_cents', 'target_currency',
        'exchange_rate_id', 'rate_used', 'fee_percent',
        'source_type', 'source_id',
        'user_id', 'idempotency_key', 'converted_at', 'metadata',
    ];

    protected $casts = [
        'source_amount_cents' => 'integer',
        'target_amount_cents' => 'integer',
        'rate_used' => 'decimal:8',
        'fee_percent' => 'decimal:4',
        'converted_at' => 'datetime',
        'metadata' => 'array',
    ];

    public function exchangeRate(): BelongsTo
    {
        return $this->belongsTo(ExchangeRate::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}

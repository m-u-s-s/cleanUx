<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

class ExchangeRate extends Model
{
    public const SOURCE_MOCK = 'mock';
    public const SOURCE_ECB = 'ecb';
    public const SOURCE_OPENEXCHANGE = 'openexchange';
    public const SOURCE_MANUAL = 'manual';
    public const SOURCE_FALLBACK = 'fallback';

    protected $fillable = [
        'base_currency', 'quote_currency', 'rate', 'source',
        'fetched_at', 'valid_from', 'valid_until',
        'idempotency_key', 'metadata',
    ];

    protected $casts = [
        'rate' => 'decimal:8',
        'fetched_at' => 'datetime',
        'valid_from' => 'datetime',
        'valid_until' => 'datetime',
        'metadata' => 'array',
    ];

    public function scopePair(Builder $q, string $base, string $quote): Builder
    {
        return $q->where('base_currency', strtoupper($base))
            ->where('quote_currency', strtoupper($quote));
    }

    public function scopeFresh(Builder $q, int $maxAgeMinutes): Builder
    {
        return $q->where('fetched_at', '>=', now()->subMinutes($maxAgeMinutes));
    }

    public function isStale(int $maxAgeHours): bool
    {
        return $this->fetched_at->lt(now()->subHours($maxAgeHours));
    }
}

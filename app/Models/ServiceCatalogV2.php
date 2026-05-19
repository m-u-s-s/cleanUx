<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

class ServiceCatalogV2 extends Model
{
    protected $table = 'service_catalog_v2';

    public const UNIT_PER_HOUR = 'per_hour';
    public const UNIT_PER_M2 = 'per_m2';
    public const UNIT_PER_VISIT = 'per_visit';
    public const UNIT_FLAT = 'flat';
    public const UNIT_PER_KG = 'per_kg';

    protected $fillable = [
        'code', 'name', 'description', 'trade_code',
        'base_price_cents', 'currency', 'unit',
        'min_price_cents', 'max_price_cents',
        'is_active', 'version', 'parent_version_id',
        'locale_overrides', 'metadata',
    ];

    protected $casts = [
        'base_price_cents' => 'integer',
        'min_price_cents' => 'integer',
        'max_price_cents' => 'integer',
        'is_active' => 'boolean',
        'version' => 'integer',
        'locale_overrides' => 'array',
        'metadata' => 'array',
    ];

    public function scopeActive(Builder $q): Builder
    {
        return $q->where('is_active', true);
    }

    public function scopeForTrade(Builder $q, string $tradeCode): Builder
    {
        return $q->where('trade_code', $tradeCode);
    }
}

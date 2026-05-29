<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * TradeZonePricing — tarif de base d'un métier dans une zone de service.
 *
 * Permet de surcharger le default_hourly_rate du Trade par zone géographique,
 * avec un multiplicateur de surge et des planchers/plafonds en centimes.
 *
 * @property int $id
 * @property int $trade_id
 * @property int $service_zone_id
 * @property int $base_rate_cents
 * @property string $surge_multiplier
 * @property int|null $min_price_cents
 * @property int|null $max_price_cents
 * @property bool $is_active
 * @property array|null $metadata
 */
class TradeZonePricing extends Model
{
    use HasFactory;

    protected $table = 'trade_zone_pricing';

    protected $fillable = [
        'trade_id',
        'service_zone_id',
        'base_rate_cents',
        'surge_multiplier',
        'min_price_cents',
        'max_price_cents',
        'is_active',
        'metadata',
    ];

    protected $casts = [
        'base_rate_cents' => 'integer',
        'surge_multiplier' => 'decimal:2',
        'min_price_cents' => 'integer',
        'max_price_cents' => 'integer',
        'is_active' => 'boolean',
        'metadata' => 'array',
    ];

    // ──────────────────────────────────────────────────────
    // Relations
    // ──────────────────────────────────────────────────────

    public function trade(): BelongsTo
    {
        return $this->belongsTo(Trade::class);
    }

    public function serviceZone(): BelongsTo
    {
        return $this->belongsTo(ServiceZone::class);
    }

    // ──────────────────────────────────────────────────────
    // Scopes
    // ──────────────────────────────────────────────────────

    public function scopeActive(Builder $q): Builder
    {
        return $q->where('is_active', true);
    }

    public function scopeForTrade(Builder $q, int $tradeId): Builder
    {
        return $q->where('trade_id', $tradeId);
    }

    // ──────────────────────────────────────────────────────
    // Accessors / computed
    // ──────────────────────────────────────────────────────

    /** Effective price after surge, clamped to min/max. */
    public function effectiveRateCents(float $multiplier = 1.0): int
    {
        $rate = (int) round($this->base_rate_cents * (float) $this->surge_multiplier * $multiplier);

        if ($this->min_price_cents !== null) {
            $rate = max($rate, $this->min_price_cents);
        }

        if ($this->max_price_cents !== null) {
            $rate = min($rate, $this->max_price_cents);
        }

        return $rate;
    }

    // ──────────────────────────────────────────────────────
    // Legacy accessors (kept for backward compat)
    // ──────────────────────────────────────────────────────

    /** Taux de base en euros (float). */
    public function getBaseRateEurosAttribute(): float
    {
        return $this->base_rate_cents / 100;
    }

    /** Prix minimum en euros ou null. */
    public function getMinPriceEurosAttribute(): ?float
    {
        return $this->min_price_cents !== null ? $this->min_price_cents / 100 : null;
    }

    /** Prix maximum en euros ou null. */
    public function getMaxPriceEurosAttribute(): ?float
    {
        return $this->max_price_cents !== null ? $this->max_price_cents / 100 : null;
    }
}

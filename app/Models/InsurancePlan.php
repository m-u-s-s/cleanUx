<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

class InsurancePlan extends Model
{
    protected $fillable = [
        'code', 'name', 'description', 'trade_codes',
        'coverage_amount_cents', 'premium_base_cents', 'premium_percent',
        'min_premium_cents', 'max_premium_cents', 'currency',
        'is_active', 'terms_url', 'valid_from', 'valid_until', 'metadata',
    ];

    protected $casts = [
        'trade_codes' => 'array',
        'coverage_amount_cents' => 'integer',
        'premium_base_cents' => 'integer',
        'premium_percent' => 'decimal:4',
        'min_premium_cents' => 'integer',
        'max_premium_cents' => 'integer',
        'is_active' => 'boolean',
        'valid_from' => 'date',
        'valid_until' => 'date',
        'metadata' => 'array',
    ];

    public function appliesToTrade(?string $tradeCode): bool
    {
        $trades = $this->trade_codes;
        if (! $trades || count($trades) === 0) {
            return true;  // null = all trades
        }
        if (! $tradeCode) {
            return false;
        }
        return in_array($tradeCode, $trades, true);
    }

    public function isWithinValidity(?\DateTimeInterface $at = null): bool
    {
        $at = $at ? \Carbon\Carbon::instance($at) : now();
        if ($this->valid_from && $at < $this->valid_from) {
            return false;
        }
        if ($this->valid_until && $at > $this->valid_until) {
            return false;
        }
        return true;
    }

    public function scopeActive(Builder $q): Builder
    {
        return $q->where('is_active', true);
    }
}

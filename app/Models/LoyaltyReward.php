<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class LoyaltyReward extends Model
{
    public const TYPE_DISCOUNT_CODE = 'discount_code';
    public const TYPE_SERVICE_CREDIT = 'service_credit';
    public const TYPE_PHYSICAL_ITEM = 'physical_item';
    public const TYPE_PARTNER_VOUCHER = 'partner_voucher';
    public const TYPE_CHARITY_DONATION = 'charity_donation';

    protected $fillable = [
        'code', 'name', 'description',
        'reward_type', 'category',
        'points_cost', 'value_cents', 'currency',
        'image_url', 'partner_name',
        'min_tier_level',
        'stock_remaining', 'stock_initial',
        'is_active', 'valid_from', 'valid_until',
        'metadata',
    ];

    protected $casts = [
        'points_cost' => 'integer',
        'value_cents' => 'integer',
        'min_tier_level' => 'integer',
        'stock_remaining' => 'integer',
        'stock_initial' => 'integer',
        'is_active' => 'boolean',
        'valid_from' => 'datetime',
        'valid_until' => 'datetime',
        'metadata' => 'array',
    ];

    public function redemptions(): HasMany
    {
        return $this->hasMany(LoyaltyRedemption::class, 'reward_id');
    }

    public function scopeActive(Builder $q): Builder
    {
        return $q->where('is_active', true)
            ->where(function ($w) {
                $w->whereNull('valid_from')->orWhere('valid_from', '<=', now());
            })
            ->where(function ($w) {
                $w->whereNull('valid_until')->orWhere('valid_until', '>=', now());
            });
    }

    public function scopeInStock(Builder $q): Builder
    {
        return $q->where(function ($w) {
            $w->whereNull('stock_remaining')->orWhere('stock_remaining', '>', 0);
        });
    }

    public function isInStock(): bool
    {
        return $this->stock_remaining === null || $this->stock_remaining > 0;
    }

    public function valueFormatted(): string
    {
        if (! $this->value_cents) {
            return '';
        }
        return number_format($this->value_cents / 100, 2, ',', ' ') . ' ' . $this->currency;
    }
}

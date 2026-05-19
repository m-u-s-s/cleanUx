<?php

namespace App\Models\SubscriptionsV2;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class SubscriptionPlanV2 extends Model
{
    protected $table = 'subscription_plans_v2';

    protected $fillable = [
        'code', 'name', 'description',
        'trade_codes', 'billing_period',
        'price_cents', 'currency',
        'included_units_per_cycle', 'included_unit_type', 'overage_unit_price_cents',
        'trial_days', 'features', 'is_active', 'version', 'metadata',
    ];

    protected $casts = [
        'trade_codes' => 'array',
        'features' => 'array',
        'metadata' => 'array',
        'is_active' => 'boolean',
        'price_cents' => 'integer',
        'included_units_per_cycle' => 'integer',
        'overage_unit_price_cents' => 'integer',
        'trial_days' => 'integer',
    ];

    public function subscriptions(): HasMany
    {
        return $this->hasMany(SubscriptionV2::class, 'plan_id');
    }

    public function scopeActive(Builder $q): Builder
    {
        return $q->where('is_active', true);
    }

    public function periodDays(): int
    {
        $map = (array) config('subscriptions_v2.periods', []);
        return (int) ($map[$this->billing_period] ?? 30);
    }

    public function priceFormatted(): string
    {
        return number_format($this->price_cents / 100, 2, ',', ' ') . ' ' . $this->currency;
    }
}

<?php

namespace App\Models\SubscriptionsV2;

use Database\Factories\SubscriptionPlanV2Factory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class SubscriptionPlanV2 extends Model
{
    /**
     * LA FABRIQUE VIT DANS `Database\Factories`, PAS DANS UN SOUS-ESPACE.
     *
     * Ce modèle étant dans `App\Models\SubscriptionsV2`, Laravel cherche par défaut
     * `Database\Factories\SubscriptionsV2\SubscriptionPlanV2Factory` — qui n'existe pas. Tout appel à
     * `::factory()` échouait donc sur « Class not found », et rien ne le signalait tant qu'aucun
     * test ne l'employait.
     */
    protected static function newFactory(): SubscriptionPlanV2Factory
    {
        return SubscriptionPlanV2Factory::new();
    }

    use HasFactory;

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

    /** @return HasMany<SubscriptionV2, $this> */
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
        return number_format($this->price_cents / 100, 2, ',', ' ').' '.$this->currency;
    }
}

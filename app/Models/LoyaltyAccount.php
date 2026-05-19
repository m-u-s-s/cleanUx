<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class LoyaltyAccount extends Model
{
    protected $fillable = [
        'user_id',
        'current_tier_id',
        'lifetime_points',
        'period_points',
        'redeemable_points',
        'tier_started_at',
        'tier_evaluated_at',
        'points_period_started_at',
        'last_activity_at',
        'metadata',
    ];

    protected $casts = [
        'lifetime_points' => 'integer',
        'period_points' => 'integer',
        'redeemable_points' => 'integer',
        'tier_started_at' => 'datetime',
        'tier_evaluated_at' => 'datetime',
        'points_period_started_at' => 'datetime',
        'last_activity_at' => 'datetime',
        'metadata' => 'array',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function currentTier(): BelongsTo
    {
        return $this->belongsTo(LoyaltyTier::class, 'current_tier_id');
    }

    public function transactions(): HasMany
    {
        return $this->hasMany(LoyaltyTransaction::class);
    }

    public function tierMultiplier(): float
    {
        if (! $this->currentTier) {
            return 1.0;
        }
        $multipliers = (array) config('loyalty.tier_multipliers', []);
        return (float) ($multipliers[$this->currentTier->slug] ?? 1.0);
    }
}

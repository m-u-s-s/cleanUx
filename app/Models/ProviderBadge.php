<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class ProviderBadge extends Model
{
    public const CRITERION_MISSIONS_COUNT = 'missions_count';
    public const CRITERION_RATING_AVG = 'rating_avg';
    public const CRITERION_TIPS_RECEIVED = 'tips_received';
    public const CRITERION_TENURE_DAYS = 'tenure_days';
    public const CRITERION_LOYALTY_POINTS = 'loyalty_points';
    public const CRITERION_STREAK_5_STARS = 'streak_5stars';

    public const TIER_BRONZE = 'bronze';
    public const TIER_SILVER = 'silver';
    public const TIER_GOLD = 'gold';
    public const TIER_PLATINUM = 'platinum';

    protected $fillable = [
        'code', 'name', 'description', 'icon', 'tier',
        'criterion_type', 'threshold', 'is_active', 'metadata',
    ];

    protected $casts = [
        'threshold' => 'integer',
        'is_active' => 'boolean',
        'metadata' => 'array',
    ];

    public function awards(): HasMany
    {
        return $this->hasMany(ProviderBadgeAward::class, 'badge_id');
    }
}

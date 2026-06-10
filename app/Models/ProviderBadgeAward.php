<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ProviderBadgeAward extends Model
{
    use HasFactory;

    protected $fillable = [
        'provider_user_id', 'badge_id', 'value_at_award', 'metadata', 'awarded_at',
    ];

    protected $casts = [
        'value_at_award' => 'integer',
        'metadata' => 'array',
        'awarded_at' => 'datetime',
    ];

    /** @return BelongsTo<User, $this> */
    public function provider(): BelongsTo
    {
        return $this->belongsTo(User::class, 'provider_user_id');
    }

    /** @return BelongsTo<ProviderBadge, $this> */
    public function badge(): BelongsTo
    {
        return $this->belongsTo(ProviderBadge::class, 'badge_id');
    }
}

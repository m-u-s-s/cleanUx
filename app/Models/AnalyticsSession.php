<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class AnalyticsSession extends Model
{
    protected $fillable = [
        'session_id',
        'user_id',
        'anonymous_id',
        'source',
        'platform',
        'locale',
        'country_code',
        'first_url',
        'first_referrer',
        'user_agent_short',
        'page_count',
        'event_count',
        'started_at',
        'last_seen_at',
        'ended_at',
        'metadata',
    ];

    protected $casts = [
        'started_at' => 'datetime',
        'last_seen_at' => 'datetime',
        'ended_at' => 'datetime',
        'metadata' => 'array',
        'page_count' => 'integer',
        'event_count' => 'integer',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function events(): HasMany
    {
        return $this->hasMany(AnalyticsEvent::class, 'session_id', 'session_id');
    }

    public function isExpired(int $inactivityMinutes): bool
    {
        return $this->last_seen_at !== null
            && $this->last_seen_at->diffInMinutes(now()) >= $inactivityMinutes;
    }
}

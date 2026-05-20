<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ProviderPresence extends Model
{
    public const STATUS_ONLINE = 'online';
    public const STATUS_BUSY = 'busy';
    public const STATUS_ON_BREAK = 'on_break';
    public const STATUS_OFFLINE = 'offline';

    protected $table = 'provider_presence';

    protected $fillable = [
        'provider_user_id', 'status',
        'current_lat', 'current_lng', 'available_radius_km',
        'heartbeat_at', 'last_status_change_at', 'last_online_at',
        'online_minutes_today', 'online_minutes_week',
        'device_info', 'metadata',
    ];

    protected $casts = [
        'current_lat' => 'float',
        'current_lng' => 'float',
        'available_radius_km' => 'integer',
        'heartbeat_at' => 'datetime',
        'last_status_change_at' => 'datetime',
        'last_online_at' => 'datetime',
        'online_minutes_today' => 'integer',
        'online_minutes_week' => 'integer',
        'metadata' => 'array',
    ];

    public function provider(): BelongsTo
    {
        return $this->belongsTo(User::class, 'provider_user_id');
    }

    public function scopeOnline(Builder $q): Builder
    {
        return $q->where('status', self::STATUS_ONLINE);
    }

    public function scopeAvailable(Builder $q): Builder
    {
        return $q->whereIn('status', [self::STATUS_ONLINE]);
    }

    public function scopeReachable(Builder $q): Builder
    {
        // online ou busy (busy = en mission, peut être contacté pour file d'attente)
        return $q->whereIn('status', [self::STATUS_ONLINE, self::STATUS_BUSY]);
    }

    public function isStale(int $thresholdMinutes = 5): bool
    {
        if ($this->status === self::STATUS_OFFLINE) {
            return false;
        }
        if (! $this->heartbeat_at) {
            return true;
        }
        return $this->heartbeat_at->lt(now()->subMinutes($thresholdMinutes));
    }
}

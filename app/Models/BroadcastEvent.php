<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

class BroadcastEvent extends Model
{
    public const STATUS_QUEUED = 'queued';
    public const STATUS_SENT = 'sent';
    public const STATUS_FAILED = 'failed';

    public const AUDIENCE_PER_USER = 'per_user';
    public const AUDIENCE_PER_CHANNEL = 'per_channel';
    public const AUDIENCE_PRESENCE = 'presence';
    public const AUDIENCE_BROADCAST = 'broadcast';

    public const CATEGORY_MISSION_ETA = 'mission_eta';
    public const CATEGORY_MISSION_STATUS = 'mission_status';
    public const CATEGORY_POSITION = 'position';
    public const CATEGORY_PRESENCE = 'presence';
    public const CATEGORY_CHAT = 'chat';
    public const CATEGORY_NOTIFICATION = 'notification';

    protected $fillable = [
        'channel',
        'event_class',
        'broadcast_as',
        'audience',
        'audience_id',
        'category',
        'payload',
        'status',
        'attempts',
        'failed_reason',
        'source_type',
        'source_id',
        'idempotency_key',
        'queued_at',
        'sent_at',
        'failed_at',
    ];

    protected $casts = [
        'payload' => 'array',
        'attempts' => 'integer',
        'queued_at' => 'datetime',
        'sent_at' => 'datetime',
        'failed_at' => 'datetime',
    ];

    public function scopeForChannel(Builder $q, string $channel): Builder
    {
        return $q->where('channel', $channel);
    }

    public function scopeForCategory(Builder $q, string $category): Builder
    {
        return $q->where('category', $category);
    }

    public function scopeFailed(Builder $q): Builder
    {
        return $q->where('status', self::STATUS_FAILED);
    }
}

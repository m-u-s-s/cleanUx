<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PushNotification extends Model
{
    public const STATUS_QUEUED = 'queued';
    public const STATUS_SENT = 'sent';
    public const STATUS_DELIVERED = 'delivered';
    public const STATUS_FAILED = 'failed';
    public const STATUS_OPTED_OUT = 'opted_out';
    public const STATUS_INVALID_TOKEN = 'invalid_token';
    public const STATUS_RATE_LIMITED = 'rate_limited';

    public const CATEGORY_TRANSACTIONAL = 'transactional';
    public const CATEGORY_VERIFICATION = 'verification';
    public const CATEGORY_REMINDER = 'reminder';
    public const CATEGORY_MARKETING = 'marketing';

    protected $fillable = [
        'user_id',
        'device_token_id',
        'provider',
        'external_id',
        'title',
        'body',
        'data',
        'locale',
        'category',
        'status',
        'attempts',
        'failed_reason',
        'failure_code',
        'source_type',
        'source_id',
        'idempotency_key',
        'queued_at',
        'sent_at',
        'failed_at',
        'metadata',
    ];

    protected $casts = [
        'data' => 'array',
        'metadata' => 'array',
        'attempts' => 'integer',
        'queued_at' => 'datetime',
        'sent_at' => 'datetime',
        'failed_at' => 'datetime',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function deviceToken(): BelongsTo
    {
        return $this->belongsTo(DeviceToken::class);
    }

    public function isFinal(): bool
    {
        return in_array($this->status, [
            self::STATUS_DELIVERED,
            self::STATUS_FAILED,
            self::STATUS_OPTED_OUT,
            self::STATUS_INVALID_TOKEN,
        ], true);
    }

    public function scopeRecentForToken(Builder $q, int $tokenId, \DateTimeInterface $since): Builder
    {
        return $q->where('device_token_id', $tokenId)
            ->whereIn('status', [self::STATUS_QUEUED, self::STATUS_SENT, self::STATUS_DELIVERED])
            ->where('queued_at', '>=', $since);
    }
}

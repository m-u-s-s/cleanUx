<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class SmsMessage extends Model
{
    public const STATUS_QUEUED = 'queued';
    public const STATUS_SENT = 'sent';
    public const STATUS_DELIVERED = 'delivered';
    public const STATUS_FAILED = 'failed';
    public const STATUS_UNDELIVERED = 'undelivered';
    public const STATUS_RATE_LIMITED = 'rate_limited';
    public const STATUS_REJECTED = 'rejected';

    public const CATEGORY_TRANSACTIONAL = 'transactional';
    public const CATEGORY_VERIFICATION = 'verification';
    public const CATEGORY_REMINDER = 'reminder';
    public const CATEGORY_MARKETING = 'marketing';

    protected $fillable = [
        'provider',
        'external_id',
        'to_phone',
        'from_phone',
        'body',
        'locale',
        'status',
        'attempts',
        'failed_reason',
        'failure_code',
        'source_type',
        'source_id',
        'user_id',
        'idempotency_key',
        'category',
        'cost_eur',
        'queued_at',
        'sent_at',
        'delivered_at',
        'failed_at',
        'metadata',
    ];

    protected $casts = [
        'attempts' => 'integer',
        'cost_eur' => 'decimal:4',
        'queued_at' => 'datetime',
        'sent_at' => 'datetime',
        'delivered_at' => 'datetime',
        'failed_at' => 'datetime',
        'metadata' => 'array',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function isFinal(): bool
    {
        return in_array($this->status, [
            self::STATUS_DELIVERED,
            self::STATUS_FAILED,
            self::STATUS_UNDELIVERED,
            self::STATUS_REJECTED,
        ], true);
    }

    public function scopeRecentForPhone(Builder $q, string $phone, \DateTimeInterface $since): Builder
    {
        return $q->where('to_phone', $phone)
            ->whereIn('status', [self::STATUS_QUEUED, self::STATUS_SENT, self::STATUS_DELIVERED])
            ->where('queued_at', '>=', $since);
    }
}

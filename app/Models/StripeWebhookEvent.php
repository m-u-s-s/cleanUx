<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

class StripeWebhookEvent extends Model
{
    public const STATUS_RECEIVED = 'received';
    public const STATUS_PROCESSING = 'processing';
    public const STATUS_PROCESSED = 'processed';
    public const STATUS_IGNORED = 'ignored';
    public const STATUS_FAILED = 'failed';
    public const STATUS_DEAD_LETTER = 'dead_letter';

    protected $fillable = [
        'stripe_event_id',
        'type',
        'status',
        'payload',
        'result',
        'attempts',
        'max_attempts',
        'last_error',
        'received_at',
        'first_attempted_at',
        'processed_at',
        'next_retry_at',
        'account_id',
    ];

    protected $casts = [
        'payload' => 'array',
        'result' => 'array',
        'attempts' => 'integer',
        'max_attempts' => 'integer',
        'received_at' => 'datetime',
        'first_attempted_at' => 'datetime',
        'processed_at' => 'datetime',
        'next_retry_at' => 'datetime',
    ];

    public function isTerminal(): bool
    {
        return in_array($this->status, [
            self::STATUS_PROCESSED,
            self::STATUS_IGNORED,
            self::STATUS_DEAD_LETTER,
        ], true);
    }

    public function canRetry(): bool
    {
        return $this->status === self::STATUS_FAILED
            && $this->attempts < $this->max_attempts;
    }

    public function scopeDueForRetry(Builder $query): Builder
    {
        $now = now();
        return $query
            ->where('status', self::STATUS_FAILED)
            ->whereColumn('attempts', '<', 'max_attempts')
            ->where(function (Builder $q) use ($now) {
                $q->whereNull('next_retry_at')->orWhere('next_retry_at', '<=', $now);
            });
    }

    public function scopePending(Builder $query): Builder
    {
        return $query->whereIn('status', [self::STATUS_RECEIVED, self::STATUS_PROCESSING]);
    }
}

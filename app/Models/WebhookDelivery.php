<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class WebhookDelivery extends Model
{
    public const STATUS_PENDING = 'pending';
    public const STATUS_IN_FLIGHT = 'in_flight';
    public const STATUS_DELIVERED = 'delivered';
    public const STATUS_FAILED = 'failed';
    public const STATUS_DEAD = 'dead';
    public const STATUS_CANCELLED = 'cancelled';

    protected $fillable = [
        'event_id', 'endpoint_id', 'status',
        'attempt', 'max_attempts',
        'next_retry_at', 'last_attempted_at', 'delivered_at',
        'last_response_status', 'last_response_body', 'last_error',
        'last_latency_ms', 'signature_sent', 'idempotency_key_sent',
        'metadata',
    ];

    protected $casts = [
        'next_retry_at' => 'datetime',
        'last_attempted_at' => 'datetime',
        'delivered_at' => 'datetime',
        'attempt' => 'integer',
        'max_attempts' => 'integer',
        'last_response_status' => 'integer',
        'last_latency_ms' => 'integer',
        'metadata' => 'array',
    ];

    public function event(): BelongsTo
    {
        return $this->belongsTo(WebhookEvent::class, 'event_id');
    }

    public function endpoint(): BelongsTo
    {
        return $this->belongsTo(WebhookEndpoint::class, 'endpoint_id');
    }

    public function scopeDue(Builder $q): Builder
    {
        return $q->whereIn('status', [self::STATUS_PENDING, self::STATUS_FAILED])
            ->where(function ($w) {
                $w->whereNull('next_retry_at')->orWhere('next_retry_at', '<=', now());
            });
    }

    public function scopeNotTerminal(Builder $q): Builder
    {
        return $q->whereNotIn('status', [
            self::STATUS_DELIVERED, self::STATUS_DEAD, self::STATUS_CANCELLED,
        ]);
    }

    public function isTerminal(): bool
    {
        return in_array($this->status, [
            self::STATUS_DELIVERED, self::STATUS_DEAD, self::STATUS_CANCELLED,
        ], true);
    }
}

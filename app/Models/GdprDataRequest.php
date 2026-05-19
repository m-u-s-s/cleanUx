<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class GdprDataRequest extends Model
{
    public const TYPE_EXPORT = 'export';
    public const TYPE_ERASURE = 'erasure';
    public const TYPE_RESTRICTION = 'restriction';
    public const TYPE_RECTIFICATION = 'rectification';
    public const TYPE_OBJECTION = 'objection';

    public const STATUS_PENDING = 'pending';
    public const STATUS_PROCESSING = 'processing';
    public const STATUS_AWAITING_CONFIRMATION = 'awaiting_confirmation';
    public const STATUS_AWAITING_GRACE_PERIOD = 'awaiting_grace_period';
    public const STATUS_FULFILLED = 'fulfilled';
    public const STATUS_REJECTED = 'rejected';
    public const STATUS_EXPIRED = 'expired';
    public const STATUS_CANCELLED = 'cancelled';

    protected $fillable = [
        'user_id',
        'type',
        'status',
        'reference',
        'reason',
        'admin_response',
        'export_file_path',
        'export_file_size',
        'export_format',
        'requested_at',
        'confirmed_at',
        'grace_period_ends_at',
        'fulfilled_at',
        'expires_at',
        'ip_address',
        'user_agent',
        'processed_by_user_id',
        'metadata',
    ];

    protected $casts = [
        'requested_at' => 'datetime',
        'confirmed_at' => 'datetime',
        'grace_period_ends_at' => 'datetime',
        'fulfilled_at' => 'datetime',
        'expires_at' => 'datetime',
        'export_file_size' => 'integer',
        'metadata' => 'array',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function processedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'processed_by_user_id');
    }

    public function isReadyForExecution(): bool
    {
        if ($this->status !== self::STATUS_AWAITING_GRACE_PERIOD) {
            return false;
        }
        return $this->grace_period_ends_at !== null
            && $this->grace_period_ends_at->isPast();
    }

    public function scopeOfType(Builder $q, string $type): Builder
    {
        return $q->where('type', $type);
    }

    public function scopeReadyForExecution(Builder $q): Builder
    {
        return $q->where('status', self::STATUS_AWAITING_GRACE_PERIOD)
            ->whereNotNull('grace_period_ends_at')
            ->where('grace_period_ends_at', '<', now());
    }
}

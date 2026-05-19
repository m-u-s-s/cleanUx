<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class RiskHold extends Model
{
    public const STATUS_ACTIVE = 'active';
    public const STATUS_REVIEWED_APPROVED = 'reviewed_approved';
    public const STATUS_REVIEWED_REJECTED = 'reviewed_rejected';
    public const STATUS_EXPIRED = 'expired';
    public const STATUS_AUTO_RELEASED = 'auto_released';

    protected $fillable = [
        'user_id',
        'subject_type',
        'subject_id',
        'risk_evaluation_id',
        'status',
        'reason',
        'expires_at',
        'reviewed_by_user_id',
        'reviewed_at',
        'review_notes',
        'metadata',
    ];

    protected $casts = [
        'expires_at' => 'datetime',
        'reviewed_at' => 'datetime',
        'metadata' => 'array',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function reviewer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'reviewed_by_user_id');
    }

    public function evaluation(): BelongsTo
    {
        return $this->belongsTo(RiskEvaluation::class, 'risk_evaluation_id');
    }

    public function isActive(): bool
    {
        return $this->status === self::STATUS_ACTIVE
            && ($this->expires_at === null || $this->expires_at->isFuture());
    }

    public function scopeActive(Builder $q): Builder
    {
        return $q->where('status', self::STATUS_ACTIVE)
            ->where(function ($w) {
                $w->whereNull('expires_at')->orWhere('expires_at', '>', now());
            });
    }

    public function scopePendingReview(Builder $q): Builder
    {
        return $q->where('status', self::STATUS_ACTIVE);
    }
}

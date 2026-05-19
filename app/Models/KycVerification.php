<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class KycVerification extends Model
{
    public const STATUS_PENDING = 'pending';
    public const STATUS_IN_REVIEW = 'in_review';
    public const STATUS_AWAITING_DOCS = 'awaiting_documents';
    public const STATUS_CLEAR = 'clear';
    public const STATUS_CONSIDER = 'consider';
    public const STATUS_UNIDENTIFIED = 'unidentified';
    public const STATUS_REJECTED = 'rejected';
    public const STATUS_EXPIRED = 'expired';
    public const STATUS_CANCELLED = 'cancelled';

    public const DECISION_PENDING = 'pending';
    public const DECISION_APPROVED = 'approved';
    public const DECISION_REJECTED = 'rejected';
    public const DECISION_MANUAL_REVIEW = 'manual_review';

    public const FINAL_STATUSES = [
        self::STATUS_CLEAR, self::STATUS_REJECTED,
        self::STATUS_EXPIRED, self::STATUS_CANCELLED,
    ];

    protected $fillable = [
        'user_id',
        'provider',
        'external_applicant_id',
        'external_check_id',
        'status',
        'decision',
        'score',
        'country_code',
        'requested_checks',
        'result_summary',
        'rejection_reason',
        'reviewed_by_user_id',
        'reviewed_at',
        'started_at',
        'completed_at',
        'expires_at',
        'metadata',
    ];

    protected $casts = [
        'requested_checks' => 'array',
        'result_summary' => 'array',
        'metadata' => 'array',
        'score' => 'decimal:2',
        'reviewed_at' => 'datetime',
        'started_at' => 'datetime',
        'completed_at' => 'datetime',
        'expires_at' => 'datetime',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function reviewer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'reviewed_by_user_id');
    }

    public function checks(): HasMany
    {
        return $this->hasMany(KycCheck::class);
    }

    public function isFinal(): bool
    {
        return in_array($this->status, self::FINAL_STATUSES, true);
    }

    public function isApproved(): bool
    {
        return $this->decision === self::DECISION_APPROVED;
    }

    public function scopeForUser(Builder $q, int $userId): Builder
    {
        return $q->where('user_id', $userId);
    }

    public function scopePending(Builder $q): Builder
    {
        return $q->whereIn('status', [
            self::STATUS_PENDING,
            self::STATUS_IN_REVIEW,
            self::STATUS_AWAITING_DOCS,
        ]);
    }

    public function scopeRequiringReview(Builder $q): Builder
    {
        return $q->where('decision', self::DECISION_MANUAL_REVIEW)
            ->orWhere('status', self::STATUS_CONSIDER);
    }
}

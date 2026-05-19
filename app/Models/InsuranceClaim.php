<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class InsuranceClaim extends Model
{
    public const STATUS_FILED = 'filed';
    public const STATUS_UNDER_REVIEW = 'under_review';
    public const STATUS_INFO_REQUESTED = 'info_requested';
    public const STATUS_ACCEPTED = 'accepted';
    public const STATUS_REJECTED = 'rejected';
    public const STATUS_PAID = 'paid';
    public const STATUS_CANCELLED = 'cancelled';

    public const INCIDENT_DAMAGE = 'damage';
    public const INCIDENT_THEFT = 'theft';
    public const INCIDENT_INJURY = 'injury';
    public const INCIDENT_LIABILITY = 'liability';
    public const INCIDENT_OTHER = 'other';

    protected $fillable = [
        'booking_insurance_id', 'claimant_user_id', 'status',
        'incident_type', 'incident_description', 'incident_date',
        'amount_claimed_cents', 'amount_settled_cents', 'decision_reason',
        'external_claim_id',
        'filed_at', 'reviewed_at', 'decided_at', 'paid_at',
        'evidence', 'idempotency_key', 'metadata',
    ];

    protected $casts = [
        'incident_date' => 'date',
        'amount_claimed_cents' => 'integer',
        'amount_settled_cents' => 'integer',
        'filed_at' => 'datetime',
        'reviewed_at' => 'datetime',
        'decided_at' => 'datetime',
        'paid_at' => 'datetime',
        'evidence' => 'array',
        'metadata' => 'array',
    ];

    public function insurance(): BelongsTo
    {
        return $this->belongsTo(BookingInsurance::class, 'booking_insurance_id');
    }

    public function claimant(): BelongsTo
    {
        return $this->belongsTo(User::class, 'claimant_user_id');
    }

    public function isOpen(): bool
    {
        return in_array($this->status, [
            self::STATUS_FILED,
            self::STATUS_UNDER_REVIEW,
            self::STATUS_INFO_REQUESTED,
        ], true);
    }

    public function scopeOpen(Builder $q): Builder
    {
        return $q->whereIn('status', [
            self::STATUS_FILED, self::STATUS_UNDER_REVIEW, self::STATUS_INFO_REQUESTED,
        ]);
    }
}

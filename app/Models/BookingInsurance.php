<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class BookingInsurance extends Model
{
    public const STATUS_PROPOSED = 'proposed';
    public const STATUS_ACTIVE = 'active';
    public const STATUS_CANCELLED = 'cancelled';
    public const STATUS_EXPIRED = 'expired';
    public const STATUS_CLAIMED = 'claimed';

    protected $fillable = [
        'booking_id', 'plan_id', 'user_id', 'provider_user_id',
        'policy_number', 'premium_cents', 'coverage_amount_cents', 'currency',
        'status', 'external_provider', 'external_id',
        'purchased_at', 'effective_from', 'effective_until', 'cancelled_at',
        'idempotency_key', 'metadata',
    ];

    protected $casts = [
        'premium_cents' => 'integer',
        'coverage_amount_cents' => 'integer',
        'purchased_at' => 'datetime',
        'effective_from' => 'datetime',
        'effective_until' => 'datetime',
        'cancelled_at' => 'datetime',
        'metadata' => 'array',
    ];

    public function plan(): BelongsTo
    {
        return $this->belongsTo(InsurancePlan::class, 'plan_id');
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function provider(): BelongsTo
    {
        return $this->belongsTo(User::class, 'provider_user_id');
    }

    public function claims(): HasMany
    {
        return $this->hasMany(InsuranceClaim::class, 'booking_insurance_id');
    }

    public function isActive(): bool
    {
        return $this->status === self::STATUS_ACTIVE
            && (! $this->effective_until || $this->effective_until->isFuture());
    }

    public function scopeActive(Builder $q): Builder
    {
        return $q->where('status', self::STATUS_ACTIVE);
    }
}

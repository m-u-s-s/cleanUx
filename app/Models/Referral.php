<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Referral extends Model
{
    use HasFactory;

    public const STATUS_INVITED = 'invited';
    public const STATUS_SIGNED_UP = 'signed_up';
    public const STATUS_QUALIFIED = 'qualified';
    public const STATUS_REWARDED = 'rewarded';
    public const STATUS_EXPIRED = 'expired';
    public const STATUS_FRAUD = 'fraud_flagged';

    protected $fillable = [
        'referrer_user_id',
        'referee_user_id',
        'referee_email',
        'referral_code',
        'status',
        'qualifying_booking_id',
        'invited_at',
        'signed_up_at',
        'qualified_at',
        'rewarded_at',
        'expires_at',
        'referrer_reward_amount',
        'referee_reward_amount',
        'currency',
        'source_channel',
        'ip_signup',
        'metadata',
    ];

    protected $casts = [
        'invited_at' => 'datetime',
        'signed_up_at' => 'datetime',
        'qualified_at' => 'datetime',
        'rewarded_at' => 'datetime',
        'expires_at' => 'datetime',
        'referrer_reward_amount' => 'decimal:2',
        'referee_reward_amount' => 'decimal:2',
        'metadata' => 'array',
    ];

    public function referrer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'referrer_user_id');
    }

    public function referee(): BelongsTo
    {
        return $this->belongsTo(User::class, 'referee_user_id');
    }

    public function qualifyingBooking(): BelongsTo
    {
        return $this->belongsTo(Booking::class, 'qualifying_booking_id');
    }

    public function rewards(): HasMany
    {
        return $this->hasMany(ReferralReward::class);
    }

    public function isQualifiable(): bool
    {
        return in_array($this->status, [self::STATUS_SIGNED_UP, self::STATUS_INVITED], true)
            && (! $this->expires_at || $this->expires_at->isFuture());
    }

    public function isPaidOut(): bool
    {
        return $this->status === self::STATUS_REWARDED;
    }

    public function scopeForReferrer(Builder $query, int $userId): Builder
    {
        return $query->where('referrer_user_id', $userId);
    }

    public function scopeActive(Builder $query): Builder
    {
        return $query->whereNotIn('status', [self::STATUS_EXPIRED, self::STATUS_FRAUD]);
    }
}

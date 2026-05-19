<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class LoyaltyTransaction extends Model
{
    public const TYPE_EARN_BOOKING = 'earn_booking';
    public const TYPE_EARN_REFERRAL = 'earn_referral';
    public const TYPE_EARN_RATING = 'earn_rating';
    public const TYPE_EARN_SIGNUP = 'earn_signup_bonus';
    public const TYPE_EARN_ANNIVERSARY = 'earn_anniversary';
    public const TYPE_EARN_PROMO = 'earn_promo';
    public const TYPE_EARN_ADJUSTMENT = 'earn_adjustment';
    public const TYPE_REDEEM = 'redeem';
    public const TYPE_EXPIRE = 'expire';
    public const TYPE_PENALTY = 'penalty';
    public const TYPE_ADMIN_ADJUST = 'admin_adjust';

    public const DIRECTION_CREDIT = 'credit';
    public const DIRECTION_DEBIT = 'debit';

    protected $fillable = [
        'loyalty_account_id',
        'user_id',
        'type',
        'direction',
        'points',
        'balance_after',
        'source_type',
        'source_id',
        'idempotency_key',
        'reason',
        'actor_user_id',
        'metadata',
        'occurred_at',
    ];

    protected $casts = [
        'points' => 'integer',
        'balance_after' => 'integer',
        'metadata' => 'array',
        'occurred_at' => 'datetime',
    ];

    public function account(): BelongsTo
    {
        return $this->belongsTo(LoyaltyAccount::class, 'loyalty_account_id');
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function actor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'actor_user_id');
    }

    public function scopeWithinPeriod(Builder $q, \DateTimeInterface $from): Builder
    {
        return $q->where('occurred_at', '>=', $from);
    }
}

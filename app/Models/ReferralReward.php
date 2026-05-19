<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ReferralReward extends Model
{
    use HasFactory;

    public const ROLE_REFERRER = 'referrer';
    public const ROLE_REFEREE = 'referee';

    public const TYPE_CREDIT = 'credit';
    public const TYPE_PROMO_CODE = 'promo_code';
    public const TYPE_CASH = 'cash_payout';

    public const STATUS_PENDING = 'pending';
    public const STATUS_GRANTED = 'granted';
    public const STATUS_CONSUMED = 'consumed';
    public const STATUS_REVOKED = 'revoked';
    public const STATUS_EXPIRED = 'expired';

    protected $fillable = [
        'referral_id',
        'beneficiary_user_id',
        'role',
        'reward_type',
        'amount',
        'currency',
        'status',
        'customer_credit_id',
        'promo_code_id',
        'granted_at',
        'consumed_at',
        'revoked_at',
        'revoked_reason',
        'expires_at',
        'metadata',
    ];

    protected $casts = [
        'amount' => 'decimal:2',
        'granted_at' => 'datetime',
        'consumed_at' => 'datetime',
        'revoked_at' => 'datetime',
        'expires_at' => 'datetime',
        'metadata' => 'array',
    ];

    public function referral(): BelongsTo
    {
        return $this->belongsTo(Referral::class);
    }

    public function beneficiary(): BelongsTo
    {
        return $this->belongsTo(User::class, 'beneficiary_user_id');
    }

    public function customerCredit(): BelongsTo
    {
        return $this->belongsTo(CustomerCredit::class);
    }

    public function promoCode(): BelongsTo
    {
        return $this->belongsTo(PromoCode::class);
    }

    public function isActive(): bool
    {
        return in_array($this->status, [self::STATUS_GRANTED, self::STATUS_PENDING], true)
            && (! $this->expires_at || $this->expires_at->isFuture());
    }
}

<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ProviderWalletTransaction extends Model
{
    public const TYPE_EARNING = 'earning';
    public const TYPE_TIP = 'tip';
    public const TYPE_PAYOUT = 'payout';
    public const TYPE_PLATFORM_FEE = 'platform_fee';
    public const TYPE_REFUND_CLAWBACK = 'refund_clawback';
    public const TYPE_ADJUSTMENT_CREDIT = 'adjustment_credit';
    public const TYPE_ADJUSTMENT_DEBIT = 'adjustment_debit';
    public const TYPE_BONUS = 'bonus';

    public const DIRECTION_CREDIT = 'credit';
    public const DIRECTION_DEBIT = 'debit';

    public const STATUS_PENDING = 'pending';
    public const STATUS_AVAILABLE = 'available';
    public const STATUS_PROCESSING = 'processing';
    public const STATUS_CLEARED = 'cleared';
    public const STATUS_REVERSED = 'reversed';

    protected $fillable = [
        'provider_user_id',
        'type',
        'direction',
        'amount',
        'currency',
        'balance_after',
        'status',
        'source_type',
        'source_id',
        'stripe_payment_intent_id',
        'stripe_transfer_id',
        'stripe_payout_id',
        'stripe_refund_id',
        'idempotency_key',
        'description',
        'metadata',
        'occurred_at',
    ];

    protected $casts = [
        'amount' => 'decimal:2',
        'balance_after' => 'decimal:2',
        'metadata' => 'array',
        'occurred_at' => 'datetime',
    ];

    public function provider(): BelongsTo
    {
        return $this->belongsTo(User::class, 'provider_user_id');
    }

    public function signedAmount(): float
    {
        return $this->direction === self::DIRECTION_CREDIT
            ? (float) $this->amount
            : -1 * (float) $this->amount;
    }

    public function scopeAvailableBalance(Builder $query): Builder
    {
        return $query->whereIn('status', [
            self::STATUS_AVAILABLE,
            self::STATUS_CLEARED,
        ]);
    }

    public function scopeForProvider(Builder $query, int $userId): Builder
    {
        return $query->where('provider_user_id', $userId);
    }
}

<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Str;

class LoyaltyRedemption extends Model
{
    public const STATUS_PENDING = 'pending';
    public const STATUS_CONFIRMED = 'confirmed';
    public const STATUS_DELIVERED = 'delivered';
    public const STATUS_CANCELLED = 'cancelled';
    public const STATUS_REFUNDED = 'refunded';

    protected $fillable = [
        'code', 'user_id', 'reward_id', 'points_spent',
        'status', 'delivery_method', 'voucher_code', 'shipping_address',
        'confirmed_at', 'delivered_at', 'cancelled_at', 'cancellation_reason',
        'metadata',
    ];

    protected $casts = [
        'points_spent' => 'integer',
        'confirmed_at' => 'datetime',
        'delivered_at' => 'datetime',
        'cancelled_at' => 'datetime',
        'metadata' => 'array',
    ];

    public static function generateCode(): string
    {
        return 'red_' . Str::lower(Str::random(20));
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function reward(): BelongsTo
    {
        return $this->belongsTo(LoyaltyReward::class, 'reward_id');
    }

    public function scopeActive(Builder $q): Builder
    {
        return $q->whereIn('status', [self::STATUS_PENDING, self::STATUS_CONFIRMED, self::STATUS_DELIVERED]);
    }

    public function isCancellable(): bool
    {
        return in_array($this->status, [self::STATUS_PENDING, self::STATUS_CONFIRMED], true);
    }
}

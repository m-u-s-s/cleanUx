<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PromoCodeRedemption extends Model
{
    use HasFactory;

    public const STATUS_RESERVED = 'reserved';
    public const STATUS_APPLIED = 'applied';
    public const STATUS_REVERTED = 'reverted';
    public const STATUS_EXPIRED = 'expired';

    protected $fillable = [
        'promo_code_id',
        'user_id',
        'booking_id',
        'status',
        'booking_amount_before',
        'discount_amount',
        'booking_amount_after',
        'currency',
        'redeemed_at',
        'reverted_at',
        'reverted_reason',
        'metadata',
    ];

    protected $casts = [
        'booking_amount_before' => 'decimal:2',
        'discount_amount' => 'decimal:2',
        'booking_amount_after' => 'decimal:2',
        'redeemed_at' => 'datetime',
        'reverted_at' => 'datetime',
        'metadata' => 'array',
    ];

    public function promoCode(): BelongsTo
    {
        return $this->belongsTo(PromoCode::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function booking(): BelongsTo
    {
        return $this->belongsTo(Booking::class);
    }

    public function isApplied(): bool
    {
        return $this->status === self::STATUS_APPLIED;
    }
}

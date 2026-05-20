<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Str;

class BookingTip extends Model
{
    public const STATUS_PENDING = 'pending';
    public const STATUS_CHARGED = 'charged';
    public const STATUS_PAID_OUT = 'paid_out';
    public const STATUS_FAILED = 'failed';
    public const STATUS_REFUNDED = 'refunded';
    public const STATUS_CANCELLED = 'cancelled';

    protected $fillable = [
        'code', 'booking_id', 'client_user_id', 'provider_user_id',
        'amount_cents', 'currency', 'status',
        'stripe_payment_intent_id', 'stripe_transfer_id',
        'client_bonus_points',
        'message', 'preset_label', 'preset_percent',
        'metadata', 'charged_at', 'paid_out_at', 'refunded_at',
    ];

    protected $casts = [
        'amount_cents' => 'integer',
        'client_bonus_points' => 'integer',
        'preset_percent' => 'integer',
        'metadata' => 'array',
        'charged_at' => 'datetime',
        'paid_out_at' => 'datetime',
        'refunded_at' => 'datetime',
    ];

    public static function generateCode(): string
    {
        return 'tip_' . Str::lower(Str::random(20));
    }

    public function booking(): BelongsTo
    {
        return $this->belongsTo(Booking::class);
    }

    public function client(): BelongsTo
    {
        return $this->belongsTo(User::class, 'client_user_id');
    }

    public function provider(): BelongsTo
    {
        return $this->belongsTo(User::class, 'provider_user_id');
    }

    public function scopeCharged(Builder $q): Builder
    {
        return $q->whereIn('status', [self::STATUS_CHARGED, self::STATUS_PAID_OUT]);
    }

    public function amountFormatted(): string
    {
        return number_format($this->amount_cents / 100, 2, ',', ' ') . ' ' . $this->currency;
    }
}

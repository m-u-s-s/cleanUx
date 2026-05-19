<?php

namespace App\Models\SubscriptionsV2;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Str;

class SubscriptionV2 extends Model
{
    public const STATUS_TRIALING = 'trialing';
    public const STATUS_ACTIVE = 'active';
    public const STATUS_PAUSED = 'paused';
    public const STATUS_PAST_DUE = 'past_due';
    public const STATUS_CANCELLED = 'cancelled';
    public const STATUS_EXPIRED = 'expired';

    protected $table = 'subscriptions_v2';

    protected $fillable = [
        'code', 'plan_id', 'user_id', 'provider_user_id', 'status',
        'started_at', 'trial_ends_at',
        'current_cycle_start', 'current_cycle_end', 'next_billing_at',
        'paused_at', 'cancelled_at', 'ends_at', 'cancel_at_period_end',
        'billing_currency', 'billing_cycle_count', 'total_billed_cents',
        'consecutive_failed_charges', 'stripe_subscription_id',
        'metadata',
    ];

    protected $casts = [
        'started_at' => 'datetime',
        'trial_ends_at' => 'datetime',
        'current_cycle_start' => 'datetime',
        'current_cycle_end' => 'datetime',
        'next_billing_at' => 'datetime',
        'paused_at' => 'datetime',
        'cancelled_at' => 'datetime',
        'ends_at' => 'datetime',
        'cancel_at_period_end' => 'boolean',
        'billing_cycle_count' => 'integer',
        'total_billed_cents' => 'integer',
        'consecutive_failed_charges' => 'integer',
        'metadata' => 'array',
    ];

    public static function generateCode(): string
    {
        return 'sub_' . Str::lower(Str::random(20));
    }

    public function plan(): BelongsTo
    {
        return $this->belongsTo(SubscriptionPlanV2::class, 'plan_id');
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(\App\Models\User::class, 'user_id');
    }

    public function provider(): BelongsTo
    {
        return $this->belongsTo(\App\Models\User::class, 'provider_user_id');
    }

    public function cycles(): HasMany
    {
        return $this->hasMany(SubscriptionCycleV2::class, 'subscription_id');
    }

    public function invoices(): HasMany
    {
        return $this->hasMany(SubscriptionInvoiceV2::class, 'subscription_id');
    }

    public function scopeActive(Builder $q): Builder
    {
        return $q->whereIn('status', [self::STATUS_TRIALING, self::STATUS_ACTIVE]);
    }

    public function scopeDueForBilling(Builder $q): Builder
    {
        return $q->whereIn('status', [self::STATUS_ACTIVE, self::STATUS_TRIALING])
            ->whereNotNull('next_billing_at')
            ->where('next_billing_at', '<=', now());
    }

    public function isUsable(): bool
    {
        return in_array($this->status, [self::STATUS_TRIALING, self::STATUS_ACTIVE], true);
    }

    public function isCancelled(): bool
    {
        return in_array($this->status, [self::STATUS_CANCELLED, self::STATUS_EXPIRED], true);
    }
}

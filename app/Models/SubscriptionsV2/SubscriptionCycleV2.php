<?php

namespace App\Models\SubscriptionsV2;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class SubscriptionCycleV2 extends Model
{
    public const STATUS_PENDING = 'pending';
    public const STATUS_INVOICED = 'invoiced';
    public const STATUS_PAID = 'paid';
    public const STATUS_FAILED = 'failed';
    public const STATUS_SKIPPED = 'skipped';

    protected $table = 'subscription_cycles_v2';

    protected $fillable = [
        'subscription_id', 'cycle_number',
        'period_start', 'period_end',
        'planned_amount_cents', 'billed_amount_cents',
        'used_units', 'billing_status', 'billed_at',
        'invoice_id', 'billing_raw', 'last_error',
    ];

    protected $casts = [
        'period_start' => 'datetime',
        'period_end' => 'datetime',
        'billed_at' => 'datetime',
        'planned_amount_cents' => 'integer',
        'billed_amount_cents' => 'integer',
        'used_units' => 'integer',
        'cycle_number' => 'integer',
        'billing_raw' => 'array',
    ];

    public function subscription(): BelongsTo
    {
        return $this->belongsTo(SubscriptionV2::class, 'subscription_id');
    }

    public function invoice(): BelongsTo
    {
        return $this->belongsTo(SubscriptionInvoiceV2::class, 'invoice_id');
    }

    public function scopePending(Builder $q): Builder
    {
        return $q->whereIn('billing_status', [self::STATUS_PENDING, self::STATUS_FAILED]);
    }

    public function isPaid(): bool
    {
        return $this->billing_status === self::STATUS_PAID;
    }
}

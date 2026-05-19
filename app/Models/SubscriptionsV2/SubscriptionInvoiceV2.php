<?php

namespace App\Models\SubscriptionsV2;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Str;

class SubscriptionInvoiceV2 extends Model
{
    public const STATUS_DRAFT = 'draft';
    public const STATUS_OPEN = 'open';
    public const STATUS_PAID = 'paid';
    public const STATUS_FAILED = 'failed';
    public const STATUS_VOID = 'void';

    protected $table = 'subscription_invoices_v2';

    protected $fillable = [
        'code', 'subscription_id', 'cycle_id',
        'amount_cents', 'currency', 'status',
        'stripe_invoice_id', 'due_at', 'paid_at',
        'last_error', 'payload',
    ];

    protected $casts = [
        'due_at' => 'datetime',
        'paid_at' => 'datetime',
        'amount_cents' => 'integer',
        'payload' => 'array',
    ];

    public static function generateCode(): string
    {
        return 'inv_' . Str::lower(Str::random(20));
    }

    public function subscription(): BelongsTo
    {
        return $this->belongsTo(SubscriptionV2::class, 'subscription_id');
    }

    public function cycle(): BelongsTo
    {
        return $this->belongsTo(SubscriptionCycleV2::class, 'cycle_id');
    }

    public function scopeOpen(Builder $q): Builder
    {
        return $q->whereIn('status', [self::STATUS_DRAFT, self::STATUS_OPEN]);
    }

    public function isPaid(): bool
    {
        return $this->status === self::STATUS_PAID;
    }
}

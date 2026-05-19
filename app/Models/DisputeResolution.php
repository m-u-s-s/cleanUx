<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class DisputeResolution extends Model
{
    public const TYPE_REFUND_FULL = 'refund_full';
    public const TYPE_REFUND_PARTIAL = 'refund_partial';
    public const TYPE_CREDIT = 'credit';
    public const TYPE_PROMO_CODE = 'promo_code';
    public const TYPE_REPLACEMENT_BOOKING = 'replacement_booking';
    public const TYPE_PROVIDER_WARNING = 'provider_warning';
    public const TYPE_PROVIDER_SANCTION = 'provider_sanction';
    public const TYPE_NO_ACTION = 'no_action';
    public const TYPE_DISMISSED = 'dismissed';
    public const TYPE_OTHER = 'other';

    public const STATUS_PROPOSED = 'proposed';
    public const STATUS_APPLIED = 'applied';
    public const STATUS_FAILED = 'failed';
    public const STATUS_REVERSED = 'reversed';

    protected $fillable = [
        'complaint_case_id',
        'resolution_type',
        'amount',
        'currency',
        'explanation',
        'issued_by_user_id',
        'status',
        'external_ref',
        'stripe_refund_id',
        'promo_code_id',
        'replacement_booking_id',
        'applied_at',
        'failed_at',
        'failure_reason',
        'metadata',
    ];

    protected $casts = [
        'amount' => 'decimal:2',
        'applied_at' => 'datetime',
        'failed_at' => 'datetime',
        'metadata' => 'array',
    ];

    public function complaintCase(): BelongsTo
    {
        return $this->belongsTo(ComplaintCase::class);
    }

    public function issuedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'issued_by_user_id');
    }

    public function promoCode(): BelongsTo
    {
        return $this->belongsTo(PromoCode::class);
    }

    public function replacementBooking(): BelongsTo
    {
        return $this->belongsTo(Booking::class, 'replacement_booking_id');
    }
}

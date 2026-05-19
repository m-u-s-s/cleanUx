<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class BookingCancellationV2 extends Model
{
    protected $table = 'booking_cancellations_v2';

    protected $fillable = [
        'booking_id', 'cancelled_by_user_id', 'actor_role',
        'policy_id', 'tier_id',
        'reason_code', 'reason_text',
        'fee_percent_applied', 'fee_amount_cents',
        'refund_amount_cents', 'currency', 'refund_method',
        'exempt_applied', 'override_admin_user_id', 'override_reason',
        'booking_status_before', 'booking_status_after',
        'integrations_log',
        'idempotency_key', 'cancelled_at', 'metadata',
    ];

    protected $casts = [
        'fee_percent_applied' => 'decimal:2',
        'fee_amount_cents' => 'integer',
        'refund_amount_cents' => 'integer',
        'exempt_applied' => 'boolean',
        'integrations_log' => 'array',
        'cancelled_at' => 'datetime',
        'metadata' => 'array',
    ];

    public function policy(): BelongsTo
    {
        return $this->belongsTo(CancellationPolicy::class, 'policy_id');
    }

    public function tier(): BelongsTo
    {
        return $this->belongsTo(CancellationPolicyTier::class, 'tier_id');
    }

    public function cancelledBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'cancelled_by_user_id');
    }

    public function overriddenBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'override_admin_user_id');
    }

    public function audits(): HasMany
    {
        return $this->hasMany(CancellationAudit::class, 'cancellation_id');
    }
}

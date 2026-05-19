<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class CancellationAudit extends Model
{
    public const ACTION_CREATED = 'created';
    public const ACTION_OVERRIDDEN = 'overridden';
    public const ACTION_REFUNDED = 'refunded';
    public const ACTION_REFUND_FAILED = 'refund_failed';

    protected $fillable = [
        'cancellation_id', 'actor_user_id', 'action',
        'before_state', 'after_state', 'notes', 'occurred_at',
    ];

    protected $casts = [
        'before_state' => 'array',
        'after_state' => 'array',
        'occurred_at' => 'datetime',
    ];

    public function cancellation(): BelongsTo
    {
        return $this->belongsTo(BookingCancellationV2::class, 'cancellation_id');
    }

    public function actor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'actor_user_id');
    }
}

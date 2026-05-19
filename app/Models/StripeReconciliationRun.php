<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class StripeReconciliationRun extends Model
{
    public const SCOPE_PAYMENT_INTENTS = 'payment_intents';
    public const SCOPE_TRANSFERS = 'transfers';
    public const SCOPE_PAYOUTS = 'payouts';
    public const SCOPE_ALL = 'all';

    public const STATUS_RUNNING = 'running';
    public const STATUS_COMPLETED = 'completed';
    public const STATUS_FAILED = 'failed';

    protected $fillable = [
        'scope',
        'period_start',
        'period_end',
        'status',
        'items_checked',
        'mismatches_found',
        'auto_fixed',
        'requires_attention',
        'summary',
        'mismatches',
        'error_message',
        'started_at',
        'completed_at',
        'triggered_by_user_id',
    ];

    protected $casts = [
        'period_start' => 'date',
        'period_end' => 'date',
        'items_checked' => 'integer',
        'mismatches_found' => 'integer',
        'auto_fixed' => 'integer',
        'requires_attention' => 'integer',
        'summary' => 'array',
        'mismatches' => 'array',
        'started_at' => 'datetime',
        'completed_at' => 'datetime',
    ];

    public function triggeredBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'triggered_by_user_id');
    }
}

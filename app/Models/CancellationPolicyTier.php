<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class CancellationPolicyTier extends Model
{
    protected $fillable = [
        'policy_id', 'position',
        'min_hours_before', 'max_hours_before',
        'fee_percent', 'fee_flat_cents',
        'description', 'metadata',
    ];

    protected $casts = [
        'position' => 'integer',
        'min_hours_before' => 'integer',
        'max_hours_before' => 'integer',
        'fee_percent' => 'decimal:2',
        'fee_flat_cents' => 'integer',
        'metadata' => 'array',
    ];

    public function policy(): BelongsTo
    {
        return $this->belongsTo(CancellationPolicy::class, 'policy_id');
    }

    public function matchesHoursBefore(int $hours): bool
    {
        if ($hours < (int) $this->min_hours_before) {
            return false;
        }
        if ($this->max_hours_before !== null && $hours >= (int) $this->max_hours_before) {
            return false;
        }
        return true;
    }
}

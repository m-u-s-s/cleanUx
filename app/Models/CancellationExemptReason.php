<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class CancellationExemptReason extends Model
{
    protected $fillable = [
        'policy_id', 'reason_code', 'label',
        'requires_proof', 'max_per_user_per_30d',
        'is_active', 'metadata',
    ];

    protected $casts = [
        'requires_proof' => 'boolean',
        'max_per_user_per_30d' => 'integer',
        'is_active' => 'boolean',
        'metadata' => 'array',
    ];

    public function policy(): BelongsTo
    {
        return $this->belongsTo(CancellationPolicy::class, 'policy_id');
    }

    public function scopeActive(Builder $q): Builder
    {
        return $q->where('is_active', true);
    }
}

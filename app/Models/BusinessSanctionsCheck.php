<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class BusinessSanctionsCheck extends Model
{
    public const STATUS_PENDING = 'pending';
    public const STATUS_CLEAR = 'clear';
    public const STATUS_MATCH = 'match';
    public const STATUS_REVIEW_REQUIRED = 'review_required';
    public const STATUS_ERROR = 'error';

    protected $fillable = [
        'entity_id', 'list_name', 'status',
        'match_count', 'match_payload', 'provider',
        'checked_at', 'expires_at',
        'reviewed_by_user_id', 'reviewed_at', 'reviewer_notes',
        'metadata',
    ];

    protected $casts = [
        'match_count' => 'integer',
        'match_payload' => 'array',
        'checked_at' => 'datetime',
        'expires_at' => 'datetime',
        'reviewed_at' => 'datetime',
        'metadata' => 'array',
    ];

    public function entity(): BelongsTo
    {
        return $this->belongsTo(BusinessEntity::class, 'entity_id');
    }

    public function scopeMatches(Builder $q): Builder
    {
        return $q->where('status', self::STATUS_MATCH);
    }

    public function isMatch(): bool
    {
        return $this->status === self::STATUS_MATCH;
    }
}

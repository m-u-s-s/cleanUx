<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class BusinessDocument extends Model
{
    public const STATUS_PENDING = 'pending';
    public const STATUS_APPROVED = 'approved';
    public const STATUS_REJECTED = 'rejected';
    public const STATUS_EXPIRED = 'expired';

    protected $fillable = [
        'entity_id', 'document_type',
        'file_path', 'mime_type', 'size_bytes',
        'uploaded_at', 'uploaded_by_user_id',
        'status', 'expires_at',
        'reviewed_at', 'reviewed_by_user_id', 'rejection_reason',
        'metadata',
    ];

    protected $casts = [
        'uploaded_at' => 'datetime',
        'expires_at' => 'datetime',
        'reviewed_at' => 'datetime',
        'size_bytes' => 'integer',
        'metadata' => 'array',
    ];

    public function entity(): BelongsTo
    {
        return $this->belongsTo(BusinessEntity::class, 'entity_id');
    }

    public function scopeApproved(Builder $q): Builder
    {
        return $q->where('status', self::STATUS_APPROVED);
    }

    public function isExpired(): bool
    {
        return $this->expires_at !== null && $this->expires_at->isPast();
    }
}

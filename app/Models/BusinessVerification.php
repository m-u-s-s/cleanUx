<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class BusinessVerification extends Model
{
    public const STATUS_PENDING = 'pending';
    public const STATUS_SUCCESS = 'success';
    public const STATUS_FAILED = 'failed';
    public const STATUS_ERROR = 'error';

    protected $fillable = [
        'entity_id', 'provider', 'check_type', 'status',
        'idempotency_key', 'request_payload', 'response_payload',
        'matched_value', 'checked_at', 'expires_at', 'last_error',
    ];

    protected $casts = [
        'request_payload' => 'array',
        'response_payload' => 'array',
        'checked_at' => 'datetime',
        'expires_at' => 'datetime',
    ];

    public function entity(): BelongsTo
    {
        return $this->belongsTo(BusinessEntity::class, 'entity_id');
    }

    public function scopeFresh(Builder $q): Builder
    {
        return $q->where(function ($w) {
            $w->whereNull('expires_at')->orWhere('expires_at', '>', now());
        });
    }

    public function isFresh(): bool
    {
        return $this->expires_at === null || $this->expires_at->isFuture();
    }
}

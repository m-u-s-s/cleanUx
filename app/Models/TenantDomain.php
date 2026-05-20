<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class TenantDomain extends Model
{
    public const SSL_PENDING = 'pending';
    public const SSL_READY = 'ready';
    public const SSL_FAILED = 'failed';

    protected $fillable = [
        'tenant_id', 'domain',
        'is_primary', 'is_verified', 'verified_at',
        'ssl_status', 'certificate_expires_at',
        'metadata',
    ];

    protected $casts = [
        'is_primary' => 'boolean',
        'is_verified' => 'boolean',
        'verified_at' => 'datetime',
        'certificate_expires_at' => 'datetime',
        'metadata' => 'array',
    ];

    public function tenant(): BelongsTo
    {
        return $this->belongsTo(Tenant::class, 'tenant_id');
    }

    public function scopeVerified(Builder $q): Builder
    {
        return $q->where('is_verified', true);
    }

    public function isReady(): bool
    {
        return $this->is_verified && $this->ssl_status === self::SSL_READY;
    }
}

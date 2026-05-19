<?php

namespace App\Models\Sanctum;

use Illuminate\Database\Eloquent\Builder;
use Laravel\Sanctum\PersonalAccessToken as SanctumPersonalAccessToken;

/**
 * Extended Sanctum token with V2 features :
 * - rate_limit_per_minute, owner_role
 * - suspended_at / suspended_reason
 * - rotation chain (rotated_from_token_id + rotation_grace_until)
 * - usage tracking (last_used_ip_hash, usage_count)
 */
class PersonalAccessTokenV2 extends SanctumPersonalAccessToken
{
    protected $table = 'personal_access_tokens';

    protected $casts = [
        'abilities' => 'json',
        'last_used_at' => 'datetime',
        'expires_at' => 'datetime',
        'suspended_at' => 'datetime',
        'rotated_at' => 'datetime',
        'rotation_grace_until' => 'datetime',
        'metadata' => 'array',
        'rate_limit_per_minute' => 'integer',
        'usage_count' => 'integer',
    ];

    protected $fillable = [
        'tokenable_type', 'tokenable_id', 'name', 'token', 'abilities',
        'display_name', 'description', 'rate_limit_per_minute', 'owner_role',
        'suspended_at', 'suspended_reason',
        'rotated_from_token_id', 'rotated_at', 'rotation_grace_until',
        'last_used_at', 'last_used_ip_hash', 'usage_count',
        'expires_at', 'metadata',
    ];

    public function isSuspended(): bool
    {
        return $this->suspended_at !== null;
    }

    public function isExpired(): bool
    {
        if (! $this->expires_at) {
            return false;
        }
        return $this->expires_at->isPast();
    }

    public function isRotatedExpired(): bool
    {
        if (! $this->rotation_grace_until) {
            return false;
        }
        return $this->rotation_grace_until->isPast();
    }

    public function isUsable(): bool
    {
        return ! $this->isSuspended() && ! $this->isExpired() && ! $this->isRotatedExpired();
    }

    public function effectiveRateLimit(): int
    {
        if ($this->rate_limit_per_minute) {
            return (int) $this->rate_limit_per_minute;
        }
        if ($this->owner_role === 'admin') {
            return (int) config('api_tokens_v2.admin_rate_limit_per_minute', 600);
        }
        return (int) config('api_tokens_v2.default_rate_limit_per_minute', 120);
    }

    /**
     * Override Sanctum's default permission check : suspended/expired tokens never can.
     */
    public function can($ability)
    {
        if (! $this->isUsable()) {
            return false;
        }
        return parent::can($ability);
    }

    public function scopeActive(Builder $q): Builder
    {
        return $q->whereNull('suspended_at')
            ->where(function ($w) {
                $w->whereNull('expires_at')->orWhere('expires_at', '>', now());
            });
    }
}

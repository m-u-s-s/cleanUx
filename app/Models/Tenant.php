<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Tenant extends Model
{
    public const STATUS_ACTIVE = 'active';
    public const STATUS_TRIAL = 'trial';
    public const STATUS_SUSPENDED = 'suspended';
    public const STATUS_ARCHIVED = 'archived';

    protected $fillable = [
        'code', 'name', 'slug',
        'plan_code', 'status',
        'primary_domain', 'contact_email',
        'billing_owner_user_id',
        'default_locale', 'default_currency', 'default_country_code',
        'settings', 'theming', 'features',
        'trial_ends_at', 'activated_at',
        'suspended_at', 'suspended_reason', 'archived_at',
        'metadata',
    ];

    protected $casts = [
        'settings' => 'array',
        'theming' => 'array',
        'features' => 'array',
        'metadata' => 'array',
        'trial_ends_at' => 'datetime',
        'activated_at' => 'datetime',
        'suspended_at' => 'datetime',
        'archived_at' => 'datetime',
    ];

    public function domains(): HasMany
    {
        return $this->hasMany(TenantDomain::class, 'tenant_id');
    }

    public function users(): HasMany
    {
        return $this->hasMany(TenantUser::class, 'tenant_id');
    }

    public function billingOwner(): BelongsTo
    {
        return $this->belongsTo(User::class, 'billing_owner_user_id');
    }

    public function scopeUsable(Builder $q): Builder
    {
        return $q->whereIn('status', [self::STATUS_ACTIVE, self::STATUS_TRIAL]);
    }

    public function isUsable(): bool
    {
        return in_array($this->status, [self::STATUS_ACTIVE, self::STATUS_TRIAL], true);
    }

    public function isSuspended(): bool
    {
        return $this->status === self::STATUS_SUSPENDED;
    }

    public function planLimits(): array
    {
        $plans = (array) config('tenancy_v2.plans', []);
        return (array) ($plans[$this->plan_code] ?? []);
    }

    public function hasFeature(string $feature): bool
    {
        $limits = $this->planLimits();
        return (bool) ($limits[$feature] ?? false);
    }

    public function inTrial(): bool
    {
        return $this->status === self::STATUS_TRIAL
            && $this->trial_ends_at
            && $this->trial_ends_at->isFuture();
    }
}

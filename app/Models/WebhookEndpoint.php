<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Str;

class WebhookEndpoint extends Model
{
    protected $fillable = [
        'code', 'name', 'description', 'owner_user_id',
        'url', 'secret', 'headers',
        'timeout_seconds', 'max_attempts',
        'is_active', 'is_suspended', 'suspension_reason',
        'last_success_at', 'last_failure_at', 'consecutive_failures',
        'metadata',
    ];

    protected $casts = [
        'headers' => 'array',
        'is_active' => 'boolean',
        'is_suspended' => 'boolean',
        'last_success_at' => 'datetime',
        'last_failure_at' => 'datetime',
        'consecutive_failures' => 'integer',
        'timeout_seconds' => 'integer',
        'max_attempts' => 'integer',
        'metadata' => 'array',
    ];

    protected $hidden = ['secret'];

    public static function generateCode(): string
    {
        return 'whe_' . Str::lower(Str::random(20));
    }

    public static function generateSecret(): string
    {
        return 'whsec_' . Str::random(48);
    }

    public function owner(): BelongsTo
    {
        return $this->belongsTo(\App\Models\User::class, 'owner_user_id');
    }

    public function subscriptions(): HasMany
    {
        return $this->hasMany(WebhookSubscription::class, 'endpoint_id');
    }

    public function deliveries(): HasMany
    {
        return $this->hasMany(WebhookDelivery::class, 'endpoint_id');
    }

    public function scopeActive(Builder $q): Builder
    {
        return $q->where('is_active', true)->where('is_suspended', false);
    }

    public function isDeliverable(): bool
    {
        return $this->is_active && ! $this->is_suspended;
    }

    public function subscribesTo(string $eventCode): bool
    {
        return $this->subscriptions()
            ->where('event_code', $eventCode)
            ->where('is_active', true)
            ->exists();
    }
}

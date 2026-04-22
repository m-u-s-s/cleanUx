<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ActivityLog extends Model
{
    protected $fillable = [
        'user_id',
        'action',
        'domain',
        'severity',
        'is_critical',
        'target_type',
        'target_id',
        'route_name',
        'ip_address',
        'user_agent',
        'request_id',
        'service_zone_id',
        'meta',
    ];

    protected $casts = [
        'is_critical' => 'boolean',
        'meta' => 'array',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function serviceZone(): BelongsTo
    {
        return $this->belongsTo(ServiceZone::class);
    }

    public function scopeCritical(Builder $query): Builder
    {
        return $query->where('is_critical', true);
    }

    public function scopeForDomain(Builder $query, ?string $domain): Builder
    {
        return $query->when($domain, fn (Builder $builder) => $builder->where('domain', $domain));
    }

    public function scopeForSeverity(Builder $query, ?string $severity): Builder
    {
        return $query->when($severity, fn (Builder $builder) => $builder->where('severity', $severity));
    }
}

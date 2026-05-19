<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class AuditEvent extends Model
{
    public const SEVERITY_INFO = 'info';
    public const SEVERITY_WARNING = 'warning';
    public const SEVERITY_ERROR = 'error';
    public const SEVERITY_CRITICAL = 'critical';

    public const ACTOR_USER = 'user';
    public const ACTOR_SYSTEM = 'system';
    public const ACTOR_WEBHOOK = 'webhook';
    public const ACTOR_JOB = 'job';

    protected $fillable = [
        'event_type', 'domain', 'severity',
        'actor_type', 'actor_id', 'actor_label',
        'subject_type', 'subject_id', 'subject_label',
        'context', 'context_redacted',
        'ip_hash', 'user_agent_short', 'route_name', 'request_id',
        'tenant_id', 'service_zone_id',
        'retention_policy_code', 'is_pinned',
        'idempotency_key', 'occurred_at',
    ];

    protected $casts = [
        'context' => 'array',
        'context_redacted' => 'array',
        'is_pinned' => 'boolean',
        'occurred_at' => 'datetime',
    ];

    public function actor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'actor_id');
    }

    public function scopeForDomain(Builder $q, string $domain): Builder
    {
        return $q->where('domain', $domain);
    }

    public function scopeSeverity(Builder $q, string $severity): Builder
    {
        return $q->where('severity', $severity);
    }

    public function scopePinned(Builder $q): Builder
    {
        return $q->where('is_pinned', true);
    }

    public function scopeBetween(Builder $q, \DateTimeInterface $from, \DateTimeInterface $to): Builder
    {
        return $q->where('occurred_at', '>=', $from)->where('occurred_at', '<', $to);
    }
}

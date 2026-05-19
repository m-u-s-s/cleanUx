<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class RiskEvaluation extends Model
{
    public const DECISION_ALLOW = 'allow';
    public const DECISION_REVIEW = 'review';
    public const DECISION_BLOCK = 'block';

    public const CONTEXT_BOOKING_CREATE = 'booking_create';
    public const CONTEXT_PAYMENT_ATTEMPT = 'payment_attempt';
    public const CONTEXT_LOGIN = 'login';
    public const CONTEXT_SIGNUP = 'signup';

    protected $fillable = [
        'user_id',
        'subject_type',
        'subject_id',
        'context',
        'score',
        'decision',
        'reason',
        'triggered_rules',
        'ip_hash',
        'user_agent_short',
        'idempotency_key',
        'evaluated_at',
        'metadata',
    ];

    protected $casts = [
        'score' => 'integer',
        'triggered_rules' => 'array',
        'evaluated_at' => 'datetime',
        'metadata' => 'array',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function holds(): HasMany
    {
        return $this->hasMany(RiskHold::class);
    }

    public function scopeRecent(Builder $q, \DateTimeInterface $since): Builder
    {
        return $q->where('evaluated_at', '>=', $since);
    }

    public function scopeBlocked(Builder $q): Builder
    {
        return $q->where('decision', self::DECISION_BLOCK);
    }
}

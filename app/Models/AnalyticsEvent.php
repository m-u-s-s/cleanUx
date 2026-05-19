<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class AnalyticsEvent extends Model
{
    public const CATEGORY_LIFECYCLE = 'lifecycle';
    public const CATEGORY_FUNNEL = 'funnel';
    public const CATEGORY_ENGAGEMENT = 'engagement';
    public const CATEGORY_TRANSACTION = 'transaction';
    public const CATEGORY_ERROR = 'error';

    protected $fillable = [
        'event_name',
        'event_category',
        'session_id',
        'user_id',
        'anonymous_id',
        'properties',
        'source',
        'platform',
        'locale',
        'country_code',
        'url',
        'referrer',
        'user_agent_short',
        'ip_hash',
        'revenue_cents',
        'currency',
        'idempotency_key',
        'occurred_at',
    ];

    protected $casts = [
        'properties' => 'array',
        'revenue_cents' => 'integer',
        'occurred_at' => 'datetime',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function scopeBetween(Builder $q, \DateTimeInterface $from, \DateTimeInterface $to): Builder
    {
        return $q->where('occurred_at', '>=', $from)->where('occurred_at', '<', $to);
    }

    public function scopeNamed(Builder $q, string $name): Builder
    {
        return $q->where('event_name', $name);
    }
}

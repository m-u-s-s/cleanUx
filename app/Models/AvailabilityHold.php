<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class AvailabilityHold extends Model
{
    protected $fillable = [
        'provider_user_id',
        'booking_id',
        'starts_at',
        'ends_at',
        'reason',
        'expires_at',
        'released_at',
        'idempotency_key',
        'metadata',
    ];

    protected $casts = [
        'starts_at' => 'datetime',
        'ends_at' => 'datetime',
        'expires_at' => 'datetime',
        'released_at' => 'datetime',
        'metadata' => 'array',
    ];

    public function provider(): BelongsTo
    {
        return $this->belongsTo(User::class, 'provider_user_id');
    }

    public function isActive(): bool
    {
        return $this->released_at === null && $this->expires_at?->isFuture();
    }

    public function scopeActive(Builder $q): Builder
    {
        return $q->whereNull('released_at')
            ->where('expires_at', '>', now());
    }

    public function scopeForProvider(Builder $q, int $providerId): Builder
    {
        return $q->where('provider_user_id', $providerId);
    }

    public function scopeOverlapping(Builder $q, \DateTimeInterface $startsAt, \DateTimeInterface $endsAt): Builder
    {
        return $q->where('starts_at', '<', $endsAt)
            ->where('ends_at', '>', $startsAt);
    }
}

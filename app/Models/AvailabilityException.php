<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class AvailabilityException extends Model
{
    public const TYPE_CLOSED = 'closed';
    public const TYPE_OPEN_OVERRIDE = 'open_override';
    public const TYPE_PARTIAL = 'partial';

    protected $fillable = [
        'provider_user_id',
        'date',
        'exception_type',
        'start_time',
        'end_time',
        'reason',
        'metadata',
    ];

    protected $casts = [
        'date' => 'date',
        'metadata' => 'array',
    ];

    public function provider(): BelongsTo
    {
        return $this->belongsTo(User::class, 'provider_user_id');
    }

    public function scopeForProvider(Builder $q, int $providerId): Builder
    {
        return $q->where('provider_user_id', $providerId);
    }

    public function scopeOnDate(Builder $q, \DateTimeInterface $date): Builder
    {
        return $q->whereDate('date', $date->format('Y-m-d'));
    }

    public function scopeBetween(Builder $q, \DateTimeInterface $from, \DateTimeInterface $to): Builder
    {
        // Use range bounds rather than date strings so SQLite (which stores
        // dates with implicit 00:00:00 time) also matches correctly.
        return $q->where('date', '>=', $from->format('Y-m-d') . ' 00:00:00')
                 ->where('date', '<=', $to->format('Y-m-d') . ' 23:59:59');
    }
}

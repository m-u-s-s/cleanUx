<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class AvailabilitySlot extends Model
{
    public const WEEKDAY_SUNDAY = 0;
    public const WEEKDAY_MONDAY = 1;
    public const WEEKDAY_TUESDAY = 2;
    public const WEEKDAY_WEDNESDAY = 3;
    public const WEEKDAY_THURSDAY = 4;
    public const WEEKDAY_FRIDAY = 5;
    public const WEEKDAY_SATURDAY = 6;

    protected $fillable = [
        'provider_user_id',
        'weekday',
        'start_time',
        'end_time',
        'valid_from',
        'valid_until',
        'timezone',
        'is_active',
        'metadata',
    ];

    protected $casts = [
        'weekday' => 'integer',
        'valid_from' => 'date',
        'valid_until' => 'date',
        'is_active' => 'boolean',
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

    public function scopeActive(Builder $q): Builder
    {
        return $q->where('is_active', true);
    }

    public function appliesOn(\DateTimeInterface $date): bool
    {
        if (! $this->is_active) {
            return false;
        }

        $w = (int) (new \DateTimeImmutable($date->format('Y-m-d')))->format('w');
        if ($w !== $this->weekday) {
            return false;
        }

        if ($this->valid_from && $date < $this->valid_from) {
            return false;
        }
        if ($this->valid_until && $date > $this->valid_until) {
            return false;
        }

        return true;
    }
}

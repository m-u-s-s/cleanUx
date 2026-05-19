<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ProviderPerformanceMetric extends Model
{
    protected $fillable = [
        'user_id',
        'period_start',
        'period_end',
        'window_days',
        'offers_received',
        'offers_accepted',
        'offers_declined',
        'offers_expired',
        'missions_completed',
        'missions_cancelled_by_provider',
        'acceptance_rate',
        'completion_rate',
        'cancellation_rate',
        'avg_response_seconds',
        'rating_avg_window',
        'rating_count_window',
        'computed_at',
    ];

    protected $casts = [
        'period_start' => 'date',
        'period_end' => 'date',
        'window_days' => 'integer',
        'offers_received' => 'integer',
        'offers_accepted' => 'integer',
        'offers_declined' => 'integer',
        'offers_expired' => 'integer',
        'missions_completed' => 'integer',
        'missions_cancelled_by_provider' => 'integer',
        'acceptance_rate' => 'decimal:4',
        'completion_rate' => 'decimal:4',
        'cancellation_rate' => 'decimal:4',
        'avg_response_seconds' => 'integer',
        'rating_avg_window' => 'decimal:2',
        'rating_count_window' => 'integer',
        'computed_at' => 'datetime',
    ];

    public function provider(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    public function scopeLatestForUser(Builder $query, int $userId): Builder
    {
        return $query->where('user_id', $userId)->latest('period_end');
    }
}

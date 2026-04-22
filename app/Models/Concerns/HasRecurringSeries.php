<?php

namespace App\Models\Concerns;

use Illuminate\Database\Eloquent\Relations\BelongsTo;

trait HasRecurringSeries
{
    public function seriesMaster(): BelongsTo
    {
        return $this->belongsTo(self::class, 'recurring_series_id', 'recurring_series_id')
            ->where('is_series_master', true);
    }

    public function occurrences()
    {
        return $this->hasMany(self::class, 'recurring_series_id', 'recurring_series_id')
            ->orderBy('series_position')
            ->orderBy('date')
            ->orderBy('heure');
    }

    public function scopeSeriesMaster($query)
    {
        return $query->where('is_series_master', true);
    }

    public function scopeSeriesOccurrences($query, string $seriesId)
    {
        return $query->where('recurring_series_id', $seriesId)
            ->orderBy('series_position')
            ->orderBy('date')
            ->orderBy('heure');
    }

    public function isRecurringSeries(): bool
    {
        return filled($this->recurring_series_id);
    }

    public function isSeriesPaused(): bool
    {
        return $this->series_status === 'paused';
    }

    public function isSeriesCancelled(): bool
    {
        return $this->series_status === 'cancelled';
    }

    public function scopeActiveSeries($query)
    {
        return $query->whereIn('series_status', ['active', null]);
    }
}

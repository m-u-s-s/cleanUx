<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

class DistanceCalculation extends Model
{
    protected $fillable = [
        'provider', 'signature_hash',
        'origin_lat', 'origin_lng', 'dest_lat', 'dest_lng',
        'mode', 'distance_meters', 'duration_seconds',
        'is_fallback_haversine', 'raw', 'expires_at',
    ];

    protected $casts = [
        'origin_lat' => 'float',
        'origin_lng' => 'float',
        'dest_lat' => 'float',
        'dest_lng' => 'float',
        'distance_meters' => 'integer',
        'duration_seconds' => 'integer',
        'is_fallback_haversine' => 'boolean',
        'raw' => 'array',
        'expires_at' => 'datetime',
    ];

    public function scopeFresh(Builder $q): Builder
    {
        return $q->where(function ($w) {
            $w->whereNull('expires_at')->orWhere('expires_at', '>', now());
        });
    }

    public function isFresh(): bool
    {
        return $this->expires_at === null || $this->expires_at->isFuture();
    }
}

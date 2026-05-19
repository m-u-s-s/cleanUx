<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

class GeocodingCacheEntry extends Model
{
    protected $table = 'geocoding_results';

    protected $fillable = [
        'provider', 'address_hash', 'address_input', 'country_code',
        'latitude', 'longitude', 'formatted_address', 'place_id',
        'postal_code', 'locality', 'components', 'raw', 'expires_at',
    ];

    protected $casts = [
        'latitude' => 'float',
        'longitude' => 'float',
        'components' => 'array',
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

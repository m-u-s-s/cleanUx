<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

class AddressLookup extends Model
{
    protected $fillable = [
        'provider', 'query_hash', 'query', 'country_code',
        'results', 'result_count', 'queried_at', 'expires_at',
    ];

    protected $casts = [
        'results' => 'array',
        'queried_at' => 'datetime',
        'expires_at' => 'datetime',
        'result_count' => 'integer',
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

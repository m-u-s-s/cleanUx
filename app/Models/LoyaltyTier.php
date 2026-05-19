<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

class LoyaltyTier extends Model
{
    protected $fillable = [
        'slug',
        'name',
        'min_period_points',
        'rank',
        'color',
        'icon',
        'discount_percent',
        'priority_dispatch',
        'vip_support',
        'benefits',
        'is_active',
    ];

    protected $casts = [
        'min_period_points' => 'integer',
        'rank' => 'integer',
        'discount_percent' => 'decimal:2',
        'priority_dispatch' => 'boolean',
        'vip_support' => 'boolean',
        'benefits' => 'array',
        'is_active' => 'boolean',
    ];

    public function scopeActive(Builder $q): Builder
    {
        return $q->where('is_active', true);
    }

    public function scopeRanked(Builder $q): Builder
    {
        return $q->orderBy('rank')->orderBy('min_period_points');
    }
}

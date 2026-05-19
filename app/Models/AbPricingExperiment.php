<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

class AbPricingExperiment extends Model
{
    protected $fillable = [
        'code', 'name', 'description',
        'service_codes', 'variants', 'traffic_allocation',
        'starts_at', 'ends_at', 'is_active', 'metadata',
    ];

    protected $casts = [
        'service_codes' => 'array',
        'variants' => 'array',
        'traffic_allocation' => 'array',
        'starts_at' => 'datetime',
        'ends_at' => 'datetime',
        'is_active' => 'boolean',
        'metadata' => 'array',
    ];

    public function scopeRunning(Builder $q): Builder
    {
        $now = now();
        return $q->where('is_active', true)
            ->where(function ($w) use ($now) {
                $w->whereNull('starts_at')->orWhere('starts_at', '<=', $now);
            })
            ->where(function ($w) use ($now) {
                $w->whereNull('ends_at')->orWhere('ends_at', '>=', $now);
            });
    }

    public function appliesToService(string $serviceCode): bool
    {
        $services = $this->service_codes;
        if (! $services || count($services) === 0) {
            return true;
        }
        return in_array($serviceCode, $services, true);
    }
}

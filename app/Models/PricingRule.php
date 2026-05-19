<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

class PricingRule extends Model
{
    protected $fillable = [
        'code', 'name', 'description',
        'service_code', 'trade_code', 'priority', 'is_active',
        'applies_when', 'adjustments',
        'valid_from', 'valid_until', 'metadata',
    ];

    protected $casts = [
        'priority' => 'integer',
        'is_active' => 'boolean',
        'applies_when' => 'array',
        'adjustments' => 'array',
        'valid_from' => 'date',
        'valid_until' => 'date',
        'metadata' => 'array',
    ];

    public function scopeActive(Builder $q): Builder
    {
        return $q->where('is_active', true);
    }

    public function isWithinValidity(?\DateTimeInterface $at = null): bool
    {
        $at = $at ? \Carbon\Carbon::instance($at) : now();
        if ($this->valid_from && $at < $this->valid_from) {
            return false;
        }
        if ($this->valid_until && $at > $this->valid_until) {
            return false;
        }
        return true;
    }
}

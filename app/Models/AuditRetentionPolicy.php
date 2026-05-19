<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

class AuditRetentionPolicy extends Model
{
    protected $fillable = [
        'code', 'name', 'domain', 'retention_days',
        'applies_to_severity', 'is_active',
    ];

    protected $casts = [
        'retention_days' => 'integer',
        'applies_to_severity' => 'array',
        'is_active' => 'boolean',
    ];

    public function scopeActive(Builder $q): Builder
    {
        return $q->where('is_active', true);
    }

    public function appliesToSeverity(string $severity): bool
    {
        if (! $this->applies_to_severity) {
            return true;
        }
        return in_array($severity, $this->applies_to_severity, true);
    }
}

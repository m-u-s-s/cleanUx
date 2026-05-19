<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

class RiskRule extends Model
{
    public const SEVERITY_LOW = 'low';
    public const SEVERITY_MEDIUM = 'medium';
    public const SEVERITY_HIGH = 'high';
    public const SEVERITY_CRITICAL = 'critical';

    protected $fillable = [
        'code',
        'name',
        'description',
        'severity',
        'score_delta',
        'is_active',
        'params',
    ];

    protected $casts = [
        'is_active' => 'boolean',
        'score_delta' => 'integer',
        'params' => 'array',
    ];

    public function scopeActive(Builder $q): Builder
    {
        return $q->where('is_active', true);
    }
}

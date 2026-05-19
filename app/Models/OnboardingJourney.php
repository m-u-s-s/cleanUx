<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class OnboardingJourney extends Model
{
    public const ROLE_CLIENT = 'client';
    public const ROLE_PROVIDER = 'provider';
    public const ROLE_ENTERPRISE = 'enterprise';

    protected $fillable = [
        'code', 'name', 'description', 'role',
        'is_active', 'version', 'applies_to_country', 'metadata',
    ];

    protected $casts = [
        'is_active' => 'boolean',
        'version' => 'integer',
        'applies_to_country' => 'array',
        'metadata' => 'array',
    ];

    public function steps(): HasMany
    {
        return $this->hasMany(OnboardingStep::class, 'journey_id')->orderBy('position');
    }

    public function scopeActive(Builder $q): Builder
    {
        return $q->where('is_active', true);
    }

    public function scopeForRole(Builder $q, string $role): Builder
    {
        return $q->where('role', $role);
    }
}

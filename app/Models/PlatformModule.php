<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Builder;

class PlatformModule extends Model
{
    use HasFactory;

    public const STRATEGIES = ['global', 'role', 'plan', 'zone', 'organization'];

    protected $fillable = [
        'key',
        'name',
        'description',
        'category',
        'rollout_strategy',
        'is_enabled',
        'is_locked',
        'settings',
        'sort_order',
    ];

    protected $casts = [
        'is_enabled' => 'boolean',
        'is_locked' => 'boolean',
        'settings' => 'array',
    ];

    public function scopeEnabled(Builder $query): Builder
    {
        return $query->where('is_enabled', true);
    }

    public function settingsValue(string $key, mixed $default = null): mixed
    {
        return data_get($this->settings ?? [], $key, $default);
    }

    public function settingsList(string $key): array
    {
        return collect((array) $this->settingsValue($key, []))
            ->filter(static fn ($value) => filled($value))
            ->map(static fn ($value) => is_numeric($value) ? (int) $value : (string) $value)
            ->values()
            ->all();
    }

    public function audienceSummary(): array
    {
        return [
            'roles' => $this->settingsList('allowed_roles'),
            'plans' => $this->settingsList('allowed_plans'),
            'organization_ids' => $this->settingsList('allowed_organization_ids'),
            'zone_ids' => $this->settingsList('allowed_zone_ids'),
        ];
    }
}

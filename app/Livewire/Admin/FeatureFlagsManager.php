<?php

namespace App\Livewire\Admin;

use App\Models\FeatureFlagOverride;
use App\Support\ActivityLogger;
use App\Support\Livewire\Concerns\EnforcesAdminAccess;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Auth;
use Livewire\Attributes\Layout;
use Livewire\Component;

/** FeatureFlagsManager — admin UI to toggle feature flags at runtime. */
#[Layout('layouts.app')]
class FeatureFlagsManager extends Component
{
    use EnforcesAdminAccess;

    public string $search = '';

    /** flag_key => is_enabled being edited */
    public ?string $editingKey = null;

    public bool $editingValue = false;

    public string $editReason = '';

    public function toggleFlag(string $key): void
    {
        $this->guardAdmin('admin');

        $override = FeatureFlagOverride::firstOrNew(['flag_key' => $key]);
        $current = $override->exists ? $override->is_enabled : $this->configValue($key);

        $override->fill([
            'is_enabled' => ! $current,
            'reason' => 'Quick toggle via admin UI',
            'updated_by_user_id' => Auth::id(),
        ])->save();

        ActivityLogger::log('feature_flag.toggled', $override, [
            'flag_key' => $key,
            'is_enabled' => $override->is_enabled,
        ]);

        session()->flash('success', "Flag [{$key}] set to ".($override->is_enabled ? 'enabled' : 'disabled').'.');
    }

    public function editFlag(string $key): void
    {
        $this->editingKey = $key;
        $this->editingValue = $this->resolvedValue($key);
        $this->editReason = '';
    }

    public function saveFlag(): void
    {
        $this->guardAdmin('admin');

        $this->validate([
            'editingKey' => ['required', 'string', 'max:100'],
            'editReason' => ['nullable', 'string', 'max:255'],
        ]);

        $override = FeatureFlagOverride::updateOrCreate(
            ['flag_key' => $this->editingKey],
            [
                'is_enabled' => $this->editingValue,
                'reason' => $this->editReason ?: null,
                'updated_by_user_id' => Auth::id(),
            ]
        );

        ActivityLogger::log('feature_flag.updated', $override, [
            'flag_key' => $this->editingKey,
            'is_enabled' => $override->is_enabled,
            'reason' => $override->reason,
        ]);

        $this->editingKey = null;
        session()->flash('success', "Flag [{$override->flag_key}] updated.");
    }

    public function removeOverride(string $key): void
    {
        $this->guardAdmin('admin');

        FeatureFlagOverride::where('flag_key', $key)->delete();

        ActivityLogger::log('feature_flag.override_removed', null, ['flag_key' => $key]);

        session()->flash('success', "Override for [{$key}] removed — config file value restored.");
    }

    public function render(): View
    {
        $configFlags = config('features', []);
        $overrides = FeatureFlagOverride::all()->keyBy('flag_key');
        $search = strtolower(trim($this->search));

        $flags = collect($configFlags)
            ->map(fn ($value, string $key) => $this->buildFlagRow($key, $value, $overrides))
            ->when($search, fn ($c) => $c->filter(fn ($row) => str_contains(strtolower($row['key']), $search)))
            ->values();

        return view('livewire.admin.feature-flags-manager', compact('flags'));
    }

    // ──────────────────────────────────────────────────────────────
    // Helpers
    // ──────────────────────────────────────────────────────────────

    private function buildFlagRow(string $key, mixed $configValue, Collection $overrides): array
    {
        /** @var FeatureFlagOverride|null $override */
        $override = $overrides->get($key);

        $configEnabled = $this->parsedConfigBool($configValue);
        $resolvedEnabled = $override ? $override->is_enabled : $configEnabled;

        return [
            'key' => $key,
            'config_value' => $configValue,
            'config_enabled' => $configEnabled,
            'resolved_enabled' => $resolvedEnabled,
            'has_override' => $override !== null,
            'override_reason' => $override?->reason,
            'updated_by' => $override?->updatedBy?->name,
            'updated_at' => $override?->updated_at,
        ];
    }

    private function parsedConfigBool(mixed $value): bool
    {
        if (is_bool($value)) {
            return $value;
        }
        if (is_array($value)) {
            return ($value['enabled'] ?? true) !== false;
        }

        return (bool) $value;
    }

    private function configValue(string $key): bool
    {
        return $this->parsedConfigBool(config("features.{$key}", false));
    }

    private function resolvedValue(string $key): bool
    {
        $override = FeatureFlagOverride::where('flag_key', $key)->first();

        return $override ? $override->is_enabled : $this->configValue($key);
    }

    private function guardAdmin(string $guard): void
    {
        abort_unless(Auth::check() && Auth::user()->isAdmin(), 403);
    }
}

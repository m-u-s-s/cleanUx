<?php

namespace App\Livewire\Admin;

use App\Models\OrganizationAccount;
use App\Models\PlatformModule;
use App\Models\ServiceZone;
use App\Support\ActivityLogger;
use Livewire\Component;
use Illuminate\Contracts\View\View;
use Livewire\Attributes\Layout;

class PlatformModulesCenter extends Component
{
    public string $search = '';
    public string $category = '';
    public string $strategy = '';
    public string $status = '';

    public ?int $editingModuleId = null;
    public string $name = '';
    public string $description = '';
    public string $category_value = 'core';
    public string $rollout_strategy = 'global';
    public bool $is_enabled = true;
    public bool $is_locked = false;
    public array $allowed_roles = [];
    public array $allowed_plans = [];
    public array $allowed_zone_ids = [];
    public array $allowed_organization_ids = [];
    public array $denied_roles = [];
    public array $denied_plans = [];
    public bool $allow_all_organizations = false;

    protected $queryString = ['search', 'category', 'strategy', 'status'];

    public function mount(): void
    {
        $this->loadFirstModule();
    }

    public function loadFirstModule(): void
    {
        $first = PlatformModule::query()->orderBy('sort_order')->orderBy('name')->first();

        if ($first) {
            $this->editModule($first->id);
        }
    }

    public function updatedSearch(): void
    {
        if (! $this->editingModuleId) {
            $this->loadFirstModule();
        }
    }

    public function editModule(int $moduleId): void
    {
        $module = PlatformModule::query()->findOrFail($moduleId);
        $settings = $module->settings ?? [];

        $this->editingModuleId = $module->id;
        $this->name = (string) $module->name;
        $this->description = (string) ($module->description ?? '');
        $this->category_value = (string) $module->category;
        $this->rollout_strategy = (string) $module->rollout_strategy;
        $this->is_enabled = (bool) $module->is_enabled;
        $this->is_locked = (bool) $module->is_locked;
        $this->allowed_roles = $this->normalizeStringArray($settings['allowed_roles'] ?? []);
        $this->allowed_plans = $this->normalizeStringArray($settings['allowed_plans'] ?? []);
        $this->allowed_zone_ids = $this->normalizeIntArray($settings['allowed_zone_ids'] ?? []);
        $this->allowed_organization_ids = $this->normalizeIntArray($settings['allowed_organization_ids'] ?? []);
        $this->denied_roles = $this->normalizeStringArray($settings['denied_roles'] ?? []);
        $this->denied_plans = $this->normalizeStringArray($settings['denied_plans'] ?? []);
        $this->allow_all_organizations = (bool) ($settings['allow_all_organizations'] ?? false);
    }

    public function save(): void
    {
        $validated = $this->validate([
            'editingModuleId' => ['required', 'integer', 'exists:platform_modules,id'],
            'name' => ['required', 'string', 'max:255'],
            'description' => ['nullable', 'string', 'max:2000'],
            'category_value' => ['required', 'string', 'max:100'],
            'rollout_strategy' => ['required', 'in:global,role,plan,zone,organization'],
            'is_enabled' => ['boolean'],
            'is_locked' => ['boolean'],
        ]);

        $module = PlatformModule::query()->findOrFail($this->editingModuleId);

        if ($module->is_locked && ! $this->is_locked) {
            // unlock allowed, no-op
        }

        $module->update([
            'name' => $validated['name'],
            'description' => $validated['description'],
            'category' => $validated['category_value'],
            'rollout_strategy' => $validated['rollout_strategy'],
            'is_enabled' => $this->is_enabled,
            'is_locked' => $this->is_locked,
            'settings' => [
                'allowed_roles' => $this->normalizeStringArray($this->allowed_roles),
                'allowed_plans' => $this->normalizeStringArray($this->allowed_plans),
                'allowed_zone_ids' => $this->normalizeIntArray($this->allowed_zone_ids),
                'allowed_organization_ids' => $this->normalizeIntArray($this->allowed_organization_ids),
                'denied_roles' => $this->normalizeStringArray($this->denied_roles),
                'denied_plans' => $this->normalizeStringArray($this->denied_plans),
                'allow_all_organizations' => $this->allow_all_organizations,
            ],
        ]);

        ActivityLogger::log('platform_module.updated', $module, [
            'rollout_strategy' => $module->rollout_strategy,
            'is_enabled' => $module->is_enabled,
            'is_locked' => $module->is_locked,
        ]);

        session()->flash('success', 'Module plateforme enregistré.');
        $this->dispatch('moduleSaved');
        $this->editModule($module->id);
    }

    public function toggleEnabled(int $moduleId): void
    {
        $module = PlatformModule::query()->findOrFail($moduleId);

        if ($module->is_locked) {
            session()->flash('error', 'Ce module est verrouillé.');
            return;
        }

        $module->update(['is_enabled' => ! $module->is_enabled]);

        ActivityLogger::log('platform_module.toggled', $module, ['is_enabled' => $module->is_enabled]);

        if ($this->editingModuleId === $moduleId) {
            $this->editModule($moduleId);
        }
    }

    protected function normalizeStringArray(array $values): array
    {
        return collect($values)
            ->filter(static fn ($value) => filled($value))
            ->map(static fn ($value) => (string) $value)
            ->values()
            ->all();
    }

    protected function normalizeIntArray(array $values): array
    {
        return collect($values)
            ->filter(static fn ($value) => filled($value))
            ->map(static fn ($value) => (int) $value)
            ->values()
            ->all();
    }

    public function getModulesProperty()
    {
        return PlatformModule::query()
            ->when($this->search, function ($query) {
                $query->where(function ($sub) {
                    $sub->where('name', 'like', '%'.$this->search.'%')
                        ->orWhere('key', 'like', '%'.$this->search.'%')
                        ->orWhere('description', 'like', '%'.$this->search.'%');
                });
            })
            ->when($this->category !== '', fn ($query) => $query->where('category', $this->category))
            ->when($this->strategy !== '', fn ($query) => $query->where('rollout_strategy', $this->strategy))
            ->when($this->status !== '', function ($query) {
                if ($this->status === 'enabled') {
                    $query->where('is_enabled', true);
                } elseif ($this->status === 'disabled') {
                    $query->where('is_enabled', false);
                } elseif ($this->status === 'locked') {
                    $query->where('is_locked', true);
                }
            })
            ->orderBy('sort_order')
            ->orderBy('name')
            ->get();
    }

    public function getCategoriesProperty(): array
    {
        return PlatformModule::query()->select('category')->distinct()->orderBy('category')->pluck('category')->all();
    }

    public function getOrganizationsProperty()
    {
        return OrganizationAccount::query()->orderBy('name')->get(['id', 'name']);
    }

    public function getZonesProperty()
    {
        return ServiceZone::query()->orderBy('priority')->orderBy('name')->get(['id', 'name']);
    }

    public function render(): View
    {
        return view('livewire.admin.platform-modules-center', [
            'modules' => $this->modules,
            'categories' => $this->categories,
            'organizations' => $this->organizations,
            'zones' => $this->zones,
            'roleOptions' => ['admin', 'employe', 'client', 'entreprise'],
            'planOptions' => ['standard', 'premium'],
            'strategyOptions' => PlatformModule::STRATEGIES,
        ]);
    }
}

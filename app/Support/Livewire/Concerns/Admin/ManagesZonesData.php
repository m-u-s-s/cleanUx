<?php

namespace App\Support\Livewire\Concerns\Admin;

use App\Models\ActivityLog;
use App\Models\EmployeeZoneAssignment;
use App\Models\Province;
use App\Models\Region;
use App\Models\ServiceCatalog;
use App\Models\ServiceZone;
use App\Models\User;

trait ManagesZonesData
{
    protected function zoneBaseQuery()
    {
        return ServiceZone::query()
            ->with(['region', 'province'])
            ->withCount([
                'postalCodes',
                'organizationSites',
                'employeeAssignments as active_employee_assignments_count' => fn ($query) => $query->where('is_active', true),
                'zoneServiceRules as enabled_service_rules_count' => fn ($query) => $query->where('is_enabled', true),
                'zoneServiceRules as manual_validation_rules_count' => fn ($query) => $query->where('requires_manual_validation', true),
            ]);
    }

    protected function applyZoneFilters($query)
    {
        return $query
            ->when($this->search, function ($query) {
                $query->where(function ($subQuery) {
                    $subQuery->where('name', 'like', '%'.$this->search.'%')
                        ->orWhere('code', 'like', '%'.$this->search.'%')
                        ->orWhere('slug', 'like', '%'.$this->search.'%');
                });
            })
            ->when($this->statusFilter, fn ($query) => $query->where('status', $this->statusFilter))
            ->when($this->regionFilter, fn ($query) => $query->where('region_id', $this->regionFilter))
            ->when($this->provinceFilter, fn ($query) => $query->where('province_id', $this->provinceFilter))
            ->when($this->coverageFilter, fn ($query) => $query->where('coverage_type', $this->coverageFilter))
            ->when($this->bookableFilter !== '', fn ($query) => $query->where('is_bookable', $this->bookableFilter === '1'))
            ->when($this->visibilityFilter !== '', fn ($query) => $query->where('is_visible', $this->visibilityFilter === '1'));
    }

    public function updatingSearch(): void
    {
        $this->resetPage();
    }

    public function updatingStatusFilter(): void
    {
        $this->resetPage();
    }

    public function updatingRegionFilter(): void
    {
        $this->provinceFilter = '';
        $this->resetPage();
    }

    public function updatingProvinceFilter(): void
    {
        $this->resetPage();
    }

    public function updatingBookableFilter(): void
    {
        $this->resetPage();
    }

    public function updatingVisibilityFilter(): void
    {
        $this->resetPage();
    }

    public function updatingCoverageFilter(): void
    {
        $this->resetPage();
    }

    public function resetFilters(): void
    {
        $this->search = '';
        $this->statusFilter = '';
        $this->regionFilter = '';
        $this->provinceFilter = '';
        $this->bookableFilter = '';
        $this->visibilityFilter = '';
        $this->coverageFilter = '';
        $this->resetPage();
    }

    public function updatedSelectedZoneId($value): void
    {
        if ($value) {
            $this->selectZone((int) $value);
        }
    }

    protected function bootstrapAssignmentEdits(ServiceZone $zone): void
    {
        $this->assignmentEdits = $zone->employeeAssignments
            ->mapWithKeys(fn (EmployeeZoneAssignment $assignment) => [
                $assignment->id => [
                    'assignment_type' => $assignment->assignment_type,
                    'coverage_priority' => (int) $assignment->coverage_priority,
                    'notes' => (string) ($assignment->notes ?? ''),
                ],
            ])
            ->toArray();
    }

    public function selectZone(int $zoneId): void
    {
        $zone = $this->zoneBaseQuery()
            ->with([
                'country',
                'region',
                'province',
                'commune',
                'postalCodes' => fn ($query) => $query->orderBy('code')->orderBy('city_name'),
                'zoneServiceRules.serviceCatalog',
                'employeeAssignments.user',
            ])
            ->findOrFail($zoneId);

        $this->selectedZoneId = $zone->id;
        $this->name = (string) $zone->name;
        $this->code = (string) $zone->code;
        $this->coverage_type = (string) $zone->coverage_type;
        $this->status = (string) $zone->status;
        $this->is_bookable = (bool) $zone->is_bookable;
        $this->is_visible = (bool) $zone->is_visible;
        $this->priority = (int) $zone->priority;
        $this->minimum_notice_hours = (int) ($zone->minimum_notice_hours ?? 0);
        $this->maximum_daily_jobs = $zone->maximum_daily_jobs !== null ? (int) $zone->maximum_daily_jobs : null;
        $this->travel_surcharge = (float) ($zone->travel_surcharge ?? 0);
        $this->time_buffer_minutes = (int) ($zone->time_buffer_minutes ?? 0);
        $this->notes = (string) ($zone->notes ?? '');
        $this->employeeToAssign = '';
        $this->assignmentType = 'primary';
        $this->assignmentPriority = 100;
        $this->assignmentNotes = '';
        $this->copyRulesFromZoneId = '';

        $ruleMap = $zone->zoneServiceRules->keyBy('service_catalog_id');

        $this->serviceRules = ServiceCatalog::query()
            ->orderBy('sort_order')
            ->orderBy('name')
            ->get()
            ->mapWithKeys(function (ServiceCatalog $service) use ($ruleMap) {
                $rule = $ruleMap->get($service->id);

                return [
                    $service->id => [
                        'service_name' => $service->name,
                        'service_type' => $service->service_type,
                        'is_enabled' => (bool) ($rule->is_enabled ?? false),
                        'requires_manual_validation' => (bool) ($rule->requires_manual_validation ?? $service->requires_manual_validation),
                        'base_price_override' => $rule?->base_price_override !== null ? (string) $rule->base_price_override : '',
                        'price_multiplier' => $rule?->price_multiplier !== null ? (string) $rule->price_multiplier : '',
                        'minimum_notice_hours' => $rule?->minimum_notice_hours !== null ? (string) $rule->minimum_notice_hours : '',
                        'maximum_daily_capacity' => $rule?->maximum_daily_capacity !== null ? (string) $rule->maximum_daily_capacity : '',
                    ],
                ];
            })
            ->toArray();

        $this->bootstrapAssignmentEdits($zone);
    }

    public function getRegionsProperty()
    {
        return Region::query()->orderBy('name')->get();
    }

    public function getProvincesProperty()
    {
        return Province::query()
            ->when($this->regionFilter, fn ($query) => $query->where('region_id', $this->regionFilter))
            ->orderBy('name')
            ->get();
    }

    public function getAvailableEmployeesProperty()
    {
        return User::query()
            ->where('role', User::ROLE_EMPLOYE)
            ->where('is_active', true)
            ->orderBy('name')
            ->get();
    }

    public function getSourceZonesProperty()
    {
        return ServiceZone::query()
            ->when($this->selectedZoneId, fn ($query) => $query->where('id', '!=', $this->selectedZoneId))
            ->orderBy('priority')
            ->orderBy('name')
            ->get(['id', 'name', 'code']);
    }

    public function getSelectedZoneProperty(): ?ServiceZone
    {
        if (! $this->selectedZoneId) {
            return null;
        }

        return $this->zoneBaseQuery()
            ->with([
                'country',
                'region',
                'province',
                'commune',
                'postalCodes' => fn ($query) => $query->orderBy('code')->orderBy('city_name'),
                'zoneServiceRules.serviceCatalog',
                'employeeAssignments.user',
            ])
            ->find($this->selectedZoneId);
    }

    public function getZoneHistoryProperty()
    {
        if (! $this->selectedZoneId) {
            return collect();
        }

        return ActivityLog::query()
            ->with('user')
            ->where('target_type', ServiceZone::class)
            ->where('target_id', $this->selectedZoneId)
            ->latest()
            ->limit(12)
            ->get();
    }

    public function getZoneStatsProperty(): array
    {
        return [
            'total' => ServiceZone::count(),
            'active' => ServiceZone::where('status', 'active')->count(),
            'paused' => ServiceZone::where('status', 'paused')->count(),
            'bookable' => ServiceZone::where('is_bookable', true)->count(),
            'visible' => ServiceZone::where('is_visible', true)->count(),
        ];
    }
}

<?php

namespace App\Support\Livewire\Concerns\Admin;

use App\Models\EmployeeZoneAssignment;
use App\Models\ServiceZone;
use App\Models\User;
use App\Models\ZoneServiceRule;
use App\Support\ActivityLogger;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Gate;

trait PerformsZoneManagementActions
{
    protected function authorizeZoneManagement(bool $critical = false): void
    {
        Gate::authorize('manage-services');

        if ($critical) {
            Gate::authorize('perform-critical-admin-actions');
        }
    }

    public function saveZone(): void
    {
        $this->authorizeZoneManagement(true);

        $data = $this->validate([
            'selectedZoneId' => ['required', 'exists:service_zones,id'],
            'name' => ['required', 'string', 'max:255'],
            'status' => ['required', 'in:draft,active,paused,archived'],
            'coverage_type' => ['required', 'in:national,region,province,commune,postal_code,custom'],
            'is_bookable' => ['boolean'],
            'is_visible' => ['boolean'],
            'priority' => ['required', 'integer', 'min:1', 'max:9999'],
            'minimum_notice_hours' => ['required', 'integer', 'min:0', 'max:720'],
            'maximum_daily_jobs' => ['nullable', 'integer', 'min:1', 'max:1000'],
            'travel_surcharge' => ['required', 'numeric', 'min:0', 'max:9999.99'],
            'time_buffer_minutes' => ['required', 'integer', 'min:0', 'max:1440'],
            'notes' => ['nullable', 'string'],
        ]);

        $zone = ServiceZone::findOrFail($data['selectedZoneId']);
        $before = Arr::only($zone->toArray(), [
            'name', 'status', 'coverage_type', 'is_bookable', 'is_visible', 'priority',
            'minimum_notice_hours', 'maximum_daily_jobs', 'travel_surcharge', 'time_buffer_minutes', 'notes',
        ]);

        $zone->update([
            'name' => $data['name'],
            'status' => $data['status'],
            'coverage_type' => $data['coverage_type'],
            'is_bookable' => (bool) $data['is_bookable'],
            'is_visible' => (bool) $data['is_visible'],
            'priority' => (int) $data['priority'],
            'minimum_notice_hours' => (int) $data['minimum_notice_hours'],
            'maximum_daily_jobs' => $data['maximum_daily_jobs'] !== null ? (int) $data['maximum_daily_jobs'] : null,
            'travel_surcharge' => (float) $data['travel_surcharge'],
            'time_buffer_minutes' => (int) $data['time_buffer_minutes'],
            'notes' => $data['notes'] ?: null,
        ]);

        ActivityLogger::log('zone.updated', $zone, [
            'before' => $before,
            'after' => Arr::only($zone->fresh()->toArray(), array_keys($before)),
        ]);

        session()->flash('success', 'Zone mise à jour avec succès.');
        $this->selectZone($zone->id);
    }

    protected function persistServiceRule(ServiceZone $zone, int $serviceCatalogId, array $payload): ZoneServiceRule
    {
        return ZoneServiceRule::updateOrCreate(
            ['service_zone_id' => $zone->id, 'service_catalog_id' => $serviceCatalogId],
            [
                'is_enabled' => (bool) ($payload['is_enabled'] ?? false),
                'requires_manual_validation' => (bool) ($payload['requires_manual_validation'] ?? false),
                'base_price_override' => filled($payload['base_price_override']) ? (float) $payload['base_price_override'] : null,
                'price_multiplier' => filled($payload['price_multiplier']) ? (float) $payload['price_multiplier'] : null,
                'minimum_notice_hours' => filled($payload['minimum_notice_hours']) ? (int) $payload['minimum_notice_hours'] : null,
                'maximum_daily_capacity' => filled($payload['maximum_daily_capacity']) ? (int) $payload['maximum_daily_capacity'] : null,
            ]
        );
    }

    public function saveServiceRule(int $serviceCatalogId): void
    {
        $this->authorizeZoneManagement(true);

        $this->validate([
            'selectedZoneId' => ['required', 'exists:service_zones,id'],
            "serviceRules.$serviceCatalogId.is_enabled" => ['boolean'],
            "serviceRules.$serviceCatalogId.requires_manual_validation" => ['boolean'],
            "serviceRules.$serviceCatalogId.base_price_override" => ['nullable', 'numeric', 'min:0'],
            "serviceRules.$serviceCatalogId.price_multiplier" => ['nullable', 'numeric', 'min:0.1', 'max:10'],
            "serviceRules.$serviceCatalogId.minimum_notice_hours" => ['nullable', 'integer', 'min:0', 'max:720'],
            "serviceRules.$serviceCatalogId.maximum_daily_capacity" => ['nullable', 'integer', 'min:1', 'max:1000'],
        ]);

        $zone = ServiceZone::findOrFail($this->selectedZoneId);
        $payload = $this->serviceRules[$serviceCatalogId] ?? [];
        $rule = $this->persistServiceRule($zone, $serviceCatalogId, $payload);

        ActivityLogger::log('zone_service_rule.updated', $zone, [
            'service_catalog_id' => $serviceCatalogId,
            'rule_id' => $rule->id,
            'payload' => Arr::only($payload, [
                'is_enabled', 'requires_manual_validation', 'base_price_override',
                'price_multiplier', 'minimum_notice_hours', 'maximum_daily_capacity',
            ]),
        ]);

        session()->flash('success', 'Règle service mise à jour.');
        $this->selectZone($zone->id);
    }

    public function saveAllServiceRules(): void
    {
        $this->authorizeZoneManagement(true);

        $this->validate([
            'selectedZoneId' => ['required', 'exists:service_zones,id'],
            'serviceRules.*.is_enabled' => ['boolean'],
            'serviceRules.*.requires_manual_validation' => ['boolean'],
            'serviceRules.*.base_price_override' => ['nullable', 'numeric', 'min:0'],
            'serviceRules.*.price_multiplier' => ['nullable', 'numeric', 'min:0.1', 'max:10'],
            'serviceRules.*.minimum_notice_hours' => ['nullable', 'integer', 'min:0', 'max:720'],
            'serviceRules.*.maximum_daily_capacity' => ['nullable', 'integer', 'min:1', 'max:1000'],
        ]);

        $zone = ServiceZone::findOrFail($this->selectedZoneId);
        foreach ($this->serviceRules as $serviceCatalogId => $payload) {
            $this->persistServiceRule($zone, (int) $serviceCatalogId, $payload);
        }

        ActivityLogger::log('zone_service_rules.bulk_updated', $zone, [
            'service_catalog_ids' => array_map('intval', array_keys($this->serviceRules)),
        ]);

        session()->flash('success', 'Toutes les règles service ont été mises à jour.');
        $this->selectZone($zone->id);
    }

    public function copyServiceRulesFromZone(): void
    {
        $this->authorizeZoneManagement(true);
        $this->validate([
            'selectedZoneId' => ['required', 'exists:service_zones,id'],
            'copyRulesFromZoneId' => ['required', 'different:selectedZoneId', 'exists:service_zones,id'],
        ]);

        $zone = ServiceZone::findOrFail($this->selectedZoneId);
        $sourceZone = ServiceZone::with('zoneServiceRules')->findOrFail((int) $this->copyRulesFromZoneId);

        foreach ($sourceZone->zoneServiceRules as $rule) {
            $this->persistServiceRule($zone, $rule->service_catalog_id, [
                'is_enabled' => (bool) $rule->is_enabled,
                'requires_manual_validation' => (bool) $rule->requires_manual_validation,
                'base_price_override' => $rule->base_price_override,
                'price_multiplier' => $rule->price_multiplier,
                'minimum_notice_hours' => $rule->minimum_notice_hours,
                'maximum_daily_capacity' => $rule->maximum_daily_capacity,
            ]);
        }

        ActivityLogger::log('zone_service_rules.copied', $zone, [
            'source_zone_id' => $sourceZone->id,
            'source_zone_name' => $sourceZone->name,
        ]);

        session()->flash('success', 'Règles service copiées depuis la zone source.');
        $this->selectZone($zone->id);
    }

    public function assignEmployee(): void
    {
        $this->authorizeZoneManagement(true);

        $data = $this->validate([
            'selectedZoneId' => ['required', 'exists:service_zones,id'],
            'employeeToAssign' => ['required', 'exists:users,id'],
            'assignmentType' => ['required', 'in:primary,secondary,backup'],
            'assignmentPriority' => ['required', 'integer', 'min:1', 'max:9999'],
            'assignmentNotes' => ['nullable', 'string', 'max:1000'],
        ]);

        $zone = ServiceZone::findOrFail($data['selectedZoneId']);
        $employee = User::query()->where('role', User::ROLE_EMPLOYE)->findOrFail((int) $data['employeeToAssign']);

        $assignment = EmployeeZoneAssignment::updateOrCreate(
            ['user_id' => $employee->id, 'service_zone_id' => $zone->id],
            [
                'assignment_type' => $data['assignmentType'],
                'coverage_priority' => $data['assignmentPriority'],
                'is_active' => true,
                'starts_at' => now(),
                'ends_at' => null,
                'notes' => $data['assignmentNotes'] ?: null,
            ]
        );

        if ($assignment->assignment_type === 'primary') {
            EmployeeZoneAssignment::query()
                ->where('service_zone_id', $zone->id)
                ->where('id', '!=', $assignment->id)
                ->where('assignment_type', 'primary')
                ->update(['assignment_type' => 'secondary']);
        }

        ActivityLogger::log('zone_employe_assigne', $zone, [
            'employee_id' => $employee->id,
            'employee_name' => $employee->name,
            'assignment_type' => $assignment->assignment_type,
            'coverage_priority' => $assignment->coverage_priority,
        ]);

        session()->flash('success', 'Employé affecté à la zone.');
        $this->selectZone($zone->id);
    }

    public function saveAssignment(int $assignmentId): void
    {
        $this->authorizeZoneManagement(true);
        $this->validate([
            'selectedZoneId' => ['required', 'exists:service_zones,id'],
            "assignmentEdits.$assignmentId.assignment_type" => ['required', 'in:primary,secondary,backup'],
            "assignmentEdits.$assignmentId.coverage_priority" => ['required', 'integer', 'min:1', 'max:9999'],
            "assignmentEdits.$assignmentId.notes" => ['nullable', 'string', 'max:1000'],
        ]);

        $assignment = EmployeeZoneAssignment::with(['serviceZone', 'user'])->findOrFail($assignmentId);
        $payload = $this->assignmentEdits[$assignmentId] ?? [];
        $before = Arr::only($assignment->toArray(), ['assignment_type', 'coverage_priority', 'notes']);

        $assignment->update([
            'assignment_type' => $payload['assignment_type'],
            'coverage_priority' => $payload['coverage_priority'],
            'notes' => $payload['notes'] ?: null,
        ]);

        if ($assignment->assignment_type === 'primary') {
            EmployeeZoneAssignment::query()->where('service_zone_id', $assignment->service_zone_id)->where('id', '!=', $assignment->id)->where('assignment_type', 'primary')->update(['assignment_type' => 'secondary']);
        }

        ActivityLogger::log('zone_affectation_mise_a_jour', $assignment->serviceZone, [
            'employee_id' => $assignment->user_id,
            'employee_name' => $assignment->user?->name,
            'before' => $before,
            'after' => Arr::only($assignment->fresh()->toArray(), ['assignment_type', 'coverage_priority', 'notes']),
        ]);

        session()->flash('success', 'Affectation mise à jour.');
        $this->selectZone($assignment->service_zone_id);
    }

    public function removeAssignment(int $assignmentId): void
    {
        $this->authorizeZoneManagement(true);
        $assignment = EmployeeZoneAssignment::with(['serviceZone', 'user'])->findOrFail($assignmentId);
        $zoneId = $assignment->service_zone_id;

        ActivityLogger::log('zone_affectation_supprimee', $assignment->serviceZone, [
            'employee_id' => $assignment->user_id,
            'employee_name' => $assignment->user?->name,
            'assignment_type' => $assignment->assignment_type,
            'coverage_priority' => $assignment->coverage_priority,
        ]);

        $assignment->delete();
        session()->flash('success', 'Affectation supprimée.');
        $this->selectZone($zoneId);
    }

    public function toggleAssignment(int $assignmentId): void
    {
        $this->authorizeZoneManagement(true);
        $assignment = EmployeeZoneAssignment::with(['serviceZone', 'user'])->findOrFail($assignmentId);
        $assignment->update([
            'is_active' => ! $assignment->is_active,
            'ends_at' => $assignment->is_active ? now() : null,
            'starts_at' => $assignment->is_active ? $assignment->starts_at : now(),
        ]);

        ActivityLogger::log('zone_employe_statut_modifie', $assignment->serviceZone, [
            'employee_id' => $assignment->user_id,
            'employee_name' => $assignment->user?->name,
            'assignment_type' => $assignment->assignment_type,
            'is_active' => $assignment->fresh()->is_active,
        ]);

        session()->flash('success', 'Statut de l’affectation mis à jour.');
        $this->selectZone($assignment->service_zone_id);
    }

    public function setZoneStatus(string $status): void
    {
        $this->authorizeZoneManagement(true);
        abort_unless(in_array($status, ['draft', 'active', 'paused', 'archived'], true), 422);
        abort_unless($this->selectedZoneId !== null, 422);

        $zone = ServiceZone::findOrFail($this->selectedZoneId);
        $before = $zone->status;
        $zone->update([
            'status' => $status,
            'activated_at' => $status === 'active' ? ($zone->activated_at ?? now()) : $zone->activated_at,
            'deactivated_at' => in_array($status, ['paused', 'archived'], true) ? now() : null,
        ]);

        ActivityLogger::log('zone_statut_modifie', $zone, ['before' => $before, 'after' => $status]);
        session()->flash('success', 'Statut de zone mis à jour.');
        $this->selectZone($zone->id);
    }

    public function toggleZoneBookability(): void
    {
        $this->authorizeZoneManagement(true);
        abort_unless($this->selectedZoneId !== null, 422);
        $zone = ServiceZone::findOrFail($this->selectedZoneId);
        $before = (bool) $zone->is_bookable;
        $zone->update(['is_bookable' => ! $zone->is_bookable]);

        ActivityLogger::log('zone_bookable_modifie', $zone, ['before' => $before, 'after' => (bool) $zone->fresh()->is_bookable]);
        session()->flash('success', 'Réservabilité de la zone mise à jour.');
        $this->selectZone($zone->id);
    }

    public function toggleZoneVisibility(): void
    {
        $this->authorizeZoneManagement(true);
        abort_unless($this->selectedZoneId !== null, 422);
        $zone = ServiceZone::findOrFail($this->selectedZoneId);
        $before = (bool) $zone->is_visible;
        $zone->update(['is_visible' => ! $zone->is_visible]);

        ActivityLogger::log('zone_visibilite_modifiee', $zone, ['before' => $before, 'after' => (bool) $zone->fresh()->is_visible]);
        session()->flash('success', 'Visibilité de la zone mise à jour.');
        $this->selectZone($zone->id);
    }
}

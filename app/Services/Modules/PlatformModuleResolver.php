<?php

namespace App\Services\Modules;

use App\Models\PlatformModule;
use App\Models\User;

class PlatformModuleResolver
{
    public function isEnabledFor(string|PlatformModule $module, ?User $user = null, array $context = []): bool
    {
        $module = $module instanceof PlatformModule
            ? $module
            : PlatformModule::query()->where('key', $module)->first();

        if (! $module || ! $module->is_enabled) {
            return false;
        }

        $audience = $this->contextFor($user, $context);

        if ($this->matchesDeniedRules($module, $audience)) {
            return false;
        }

        if (! $this->matchesRolloutStrategy($module, $audience)) {
            return false;
        }

        return $this->matchesAdditionalAllowRules($module, $audience);
    }

    public function contextFor(?User $user, array $context = []): array
    {
        $userZoneIds = [];

        if ($user) {
            $userZoneIds = collect([
                $user->primary_service_zone_id,
                $user->managed_service_zone_id,
            ])->filter()->map(static fn ($id) => (int) $id)->values()->all();
        }

        $contextZoneIds = collect((array) ($context['zone_ids'] ?? []))
            ->merge(array_filter([(int) ($context['zone_id'] ?? 0)]))
            ->filter()
            ->map(static fn ($id) => (int) $id)
            ->values()
            ->all();

        return [
            'role' => $context['role'] ?? $user?->role,
            'plan_type' => $context['plan_type'] ?? $user?->plan_type,
            'organization_account_id' => $context['organization_account_id'] ?? $user?->organization_account_id,
            'zone_ids' => array_values(array_unique(array_merge($userZoneIds, $contextZoneIds))),
        ];
    }

    protected function matchesDeniedRules(PlatformModule $module, array $audience): bool
    {
        $deniedRoles = $module->settingsList('denied_roles');
        if ($deniedRoles !== [] && in_array((string) ($audience['role'] ?? ''), $deniedRoles, true)) {
            return true;
        }

        $deniedPlans = $module->settingsList('denied_plans');
        if ($deniedPlans !== [] && in_array((string) ($audience['plan_type'] ?? ''), $deniedPlans, true)) {
            return true;
        }

        $deniedOrganizations = $module->settingsList('denied_organization_ids');
        if ($deniedOrganizations !== [] && in_array((int) ($audience['organization_account_id'] ?? 0), $deniedOrganizations, true)) {
            return true;
        }

        $deniedZones = $module->settingsList('denied_zone_ids');
        if ($deniedZones !== [] && collect((array) ($audience['zone_ids'] ?? []))->intersect($deniedZones)->isNotEmpty()) {
            return true;
        }

        return false;
    }

    protected function matchesRolloutStrategy(PlatformModule $module, array $audience): bool
    {
        return match ($module->rollout_strategy) {
            'role' => $this->matchesRoleRule($module, $audience, true),
            'plan' => $this->matchesPlanRule($module, $audience, true),
            'zone' => $this->matchesZoneRule($module, $audience, true),
            'organization' => $this->matchesOrganizationRule($module, $audience, true),
            default => true,
        };
    }

    protected function matchesAdditionalAllowRules(PlatformModule $module, array $audience): bool
    {
        return $this->matchesRoleRule($module, $audience)
            && $this->matchesPlanRule($module, $audience)
            && $this->matchesOrganizationRule($module, $audience)
            && $this->matchesZoneRule($module, $audience);
    }

    protected function matchesRoleRule(PlatformModule $module, array $audience, bool $required = false): bool
    {
        $allowedRoles = $module->settingsList('allowed_roles');

        if ($allowedRoles === []) {
            return ! $required || $module->rollout_strategy !== 'role';
        }

        return in_array((string) ($audience['role'] ?? ''), $allowedRoles, true);
    }

    protected function matchesPlanRule(PlatformModule $module, array $audience, bool $required = false): bool
    {
        $allowedPlans = $module->settingsList('allowed_plans');

        if ($allowedPlans === []) {
            return ! $required || $module->rollout_strategy !== 'plan';
        }

        return in_array((string) ($audience['plan_type'] ?? ''), $allowedPlans, true);
    }

    protected function matchesOrganizationRule(PlatformModule $module, array $audience, bool $required = false): bool
    {
        $allowedOrganizations = $module->settingsList('allowed_organization_ids');

        if ($allowedOrganizations === []) {
            if ($required && $module->rollout_strategy === 'organization') {
                return (bool) $module->settingsValue('allow_all_organizations', false);
            }

            return true;
        }

        return in_array((int) ($audience['organization_account_id'] ?? 0), $allowedOrganizations, true);
    }

    protected function matchesZoneRule(PlatformModule $module, array $audience, bool $required = false): bool
    {
        $allowedZones = $module->settingsList('allowed_zone_ids');

        if ($allowedZones === []) {
            return ! $required || $module->rollout_strategy !== 'zone';
        }

        return collect((array) ($audience['zone_ids'] ?? []))->intersect($allowedZones)->isNotEmpty();
    }
}

<?php

namespace App\Services\Enterprise;

use App\Models\OrganizationAccount;
use App\Models\OrganizationSite;
use App\Models\User;

class EnterpriseRoutingService
{
    public function resolvePriorityZoneIds(?OrganizationAccount $account, ?OrganizationSite $site = null): array
    {
        $siteMeta = (array) ($site?->metadata ?? []);
        $sitePriorityIds = collect((array) ($siteMeta['zone_priority_ids'] ?? []))
            ->filter(static fn ($id) => filled($id))
            ->map(static fn ($id) => (int) $id);

        $ids = collect();

        if ($site?->service_zone_id) {
            $ids->push((int) $site->service_zone_id);
        }

        $ids = $ids
            ->merge($sitePriorityIds)
            ->merge($account?->priority_zone_ids ?? [])
            ->filter(static fn ($id) => filled($id))
            ->map(static fn ($id) => (int) $id)
            ->unique()
            ->values();

        return $ids->all();
    }

    public function buildContractSnapshot(?OrganizationAccount $account, ?OrganizationSite $site = null): array
    {
        $accountSnapshot = $account?->contractSnapshot() ?? [];
        $siteMeta = (array) ($site?->metadata ?? []);

        return [
            'account' => $accountSnapshot,
            'site' => [
                'organization_site_id' => $site?->id,
                'site_name' => $site?->name,
                'site_code' => $site?->site_code,
                'service_zone_id' => $site?->service_zone_id,
                'effective_zone_id' => $site?->effective_zone_id,
                'priority_level' => $siteMeta['priority_level'] ?? 'standard',
                'requires_manual_validation' => (bool) ($siteMeta['requires_manual_validation'] ?? false),
                'site_manager_user_id' => isset($siteMeta['site_manager_user_id']) ? (int) $siteMeta['site_manager_user_id'] : null,
                'site_tags' => array_values((array) ($siteMeta['site_tags'] ?? [])),
            ],
            'priority_zone_ids' => $this->resolvePriorityZoneIds($account, $site),
        ];
    }

    public function allowedSiteIdsForUser(User $user): array
    {
        return collect((array) data_get($user->metadata, 'entreprise_context.allowed_site_ids', []))
            ->filter(static fn ($id) => filled($id))
            ->map(static fn ($id) => (int) $id)
            ->unique()
            ->values()
            ->all();
    }

    public function userCanAccessSite(User $user, OrganizationSite $site): bool
    {
        if ($user->isAdmin()) {
            return true;
        }

        if ((int) $user->organization_account_id !== (int) $site->organization_account_id) {
            return false;
        }

        $scope = (string) data_get($user->metadata, 'entreprise_context.site_scope', 'all');

        if ($scope !== 'selected') {
            return true;
        }

        return in_array($site->id, $this->allowedSiteIdsForUser($user), true);
    }
}

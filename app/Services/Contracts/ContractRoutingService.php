<?php

namespace App\Services\Contracts;

use App\Models\OrganizationContract;

class ContractRoutingService
{
    /**
     * Pose le routage contractuel dans les DATA d'un booking (avant création).
     *
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    public function applyToBookingData(array $data, OrganizationContract $contract): array
    {
        $data['organization_contract_id'] = $contract->id;

        $hasExplicitOrg = ! empty($data['assigned_provider_organization_id']);
        $hasExplicitProvider = ! empty($data['preferred_provider_user_id']);

        if (! $hasExplicitOrg && ! $hasExplicitProvider && $contract->provider_organization_id) {
            $data['assigned_provider_organization_id'] = (int) $contract->provider_organization_id;
        }

        return $data;
    }
}

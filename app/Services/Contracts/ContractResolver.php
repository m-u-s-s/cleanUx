<?php

namespace App\Services\Contracts;

use App\Models\OrganizationAccount;
use App\Models\OrganizationContract;
use App\Models\User;
use Illuminate\Support\Carbon;

class ContractResolver
{
    /**
     * Contrat-cadre ACTIF applicable pour une org cliente, un service et une date.
     * Critères : status actif (isActiveOn), provider_organization_id renseigné,
     * service ∈ allowed_service_catalog_ids SI la liste est non vide (vide = tous).
     * Multi-contrats : le plus récent (orderByDesc effective_from puis id).
     */
    public function resolveForBooking(
        OrganizationAccount $clientOrg,
        ?int $serviceCatalogId,
        ?int $zoneId,
        string $date,
    ): ?OrganizationContract {
        $at = Carbon::parse($date);

        $candidates = OrganizationContract::query()
            ->where('organization_account_id', $clientOrg->id)
            ->whereNotNull('provider_organization_id')
            ->orderByDesc('effective_from')
            ->orderByDesc('id')
            ->get();

        return $candidates->first(function (OrganizationContract $contract) use ($at, $serviceCatalogId) {
            if (! $contract->isActiveOn($at)) {
                return false;
            }

            $allowed = $contract->allowed_service_catalog_ids ?? [];
            if (! empty($allowed) && $serviceCatalogId !== null && ! in_array($serviceCatalogId, $allowed, false)) {
                return false;
            }

            return true;
        });
    }

    /**
     * Variante depuis un User : dérive l'org cliente (current_organization_id /
     * organizationContextId) puis délègue. Soft : null si pas membre d'une org.
     */
    public function resolveForClientUser(
        User $client,
        ?int $serviceCatalogId,
        ?int $zoneId,
        string $date,
    ): ?OrganizationContract {
        $orgId = $client->organizationContextId();
        if (! $orgId) {
            return null;
        }

        $org = OrganizationAccount::find($orgId);
        if (! $org) {
            return null;
        }

        return $this->resolveForBooking($org, $serviceCatalogId, $zoneId, $date);
    }
}

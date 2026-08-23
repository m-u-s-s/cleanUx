<?php

namespace App\Services\Contracts;

use App\Exceptions\ContractPolicyException;
use App\Models\OrganizationContract;
use Illuminate\Support\Arr;

class ContractPolicyEnforcer
{
    /**
     * Enforce les policies dures d'un contrat sur les DATA d'un booking.
     *
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    public function enforceForBookingData(array $data, OrganizationContract $contract): array
    {
        $serviceCatalogId = Arr::get($data, 'service_catalog_id');

        $allowed = $contract->allowed_service_catalog_ids ?? [];
        if (! empty($allowed) && $serviceCatalogId !== null && ! in_array((int) $serviceCatalogId, $allowed, false)) {
            throw new ContractPolicyException('Ce service n’est pas couvert par votre contrat.');
        }

        if ($contract->requires_purchase_order && blank(Arr::get($data, 'purchase_order_number'))) {
            throw new ContractPolicyException('Un numéro de bon de commande (PO) est requis par votre contrat.');
        }

        if (filled($contract->default_cost_center) && blank(Arr::get($data, 'cost_center'))) {
            $data['cost_center'] = $contract->default_cost_center;
        }

        if ($contract->approval_mode === 'manual') {
            $data['entreprise_approval_required'] = true;
        }

        return $data;
    }
}

<?php

namespace App\Services\Contracts;

use App\Models\User;
use Illuminate\Support\Arr;

class ContractBookingHook
{
    public function __construct(
        private ContractResolver $resolver,
        private ContractPolicyEnforcer $policy,
        private ContractRoutingService $routing,
        private ContractPricingResolver $pricing,
    ) {}

    /**
     * Hook contrat unifié, appliqué sur $data AVANT création du booking.
     * No-op si pas de contrat applicable. Lève ContractPolicyException sur
     * violation dure (service non autorisé, PO manquant).
     *
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    public function apply(User $client, array $data, string $date): array
    {
        $serviceCatalogId = Arr::get($data, 'service_catalog_id');
        $zoneId = Arr::get($data, 'service_zone_id');

        $contract = $this->resolver->resolveForClientUser(
            $client,
            $serviceCatalogId !== null ? (int) $serviceCatalogId : null,
            $zoneId !== null ? (int) $zoneId : null,
            $date,
        );

        if (! $contract) {
            return $data;
        }

        $data = $this->policy->enforceForBookingData($data, $contract);
        $data = $this->routing->applyToBookingData($data, $contract);

        // Idempotence : le hook est appelé en couches (écran appelant → puis
        // CreateBookingAction). Routing/policy sont déjà idempotents ; on protège
        // le pricing pour ne PAS re-remiser un devis déjà négocié (double remise).
        if (Arr::get($data, 'contract_price_label') !== null) {
            return $data;
        }

        if (isset($data['devis_estime'])) {
            $res = $this->pricing->resolveAmount(
                $contract,
                $serviceCatalogId !== null ? (int) $serviceCatalogId : null,
                (float) $data['devis_estime'],
            );
            if ($res['label'] !== null) {
                $data['devis_estime'] = $res['amount'];
                $data['contract_price_label'] = $res['label'];
            }
        }

        return $data;
    }
}

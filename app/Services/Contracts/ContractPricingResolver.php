<?php

namespace App\Services\Contracts;

use App\Models\ContractRateCard;
use App\Models\OrganizationContract;

class ContractPricingResolver
{
    /**
     * Applique le tarif négocié d'un contrat à un prix de base EN CENTS.
     * Grille (ContractRateCard) prioritaire → sinon remise negotiated_discount_percent
     * → sinon no-op. Retourne le prix résultant + un label de traçabilité.
     *
     * @return array{price_cents:int, label:?string}
     */
    public function resolveCents(OrganizationContract $contract, ?int $serviceCatalogId, int $baseCents): array
    {
        if ($serviceCatalogId !== null) {
            $card = ContractRateCard::query()
                ->where('organization_contract_id', $contract->id)
                ->where('service_catalog_id', $serviceCatalogId)
                ->first();

            if ($card) {
                return ['price_cents' => (int) $card->negotiated_unit_price_cents, 'label' => 'contract:rate_card'];
            }
        }

        $discount = (float) ($contract->negotiated_discount_percent ?? 0);
        if ($discount > 0) {
            $discounted = (int) round($baseCents * (1 - $discount / 100));

            return ['price_cents' => max(0, $discounted), 'label' => 'contract:discount'];
        }

        return ['price_cents' => $baseCents, 'label' => null];
    }

    /**
     * Variante en unités décimales (€) pour le chemin booking (devis_estime).
     *
     * @return array{amount:float, label:?string}
     */
    public function resolveAmount(OrganizationContract $contract, ?int $serviceCatalogId, float $baseAmount): array
    {
        $res = $this->resolveCents($contract, $serviceCatalogId, (int) round($baseAmount * 100));

        return ['amount' => (float) ($res['price_cents'] / 100), 'label' => $res['label']];
    }
}

<?php

namespace App\Services\Contracts;

use App\Exceptions\ContractPolicyException;
use App\Models\EnterpriseWorkOrder;
use App\Models\WorkOrderLine;

class WorkOrderContractService
{
    public function __construct(private ContractPricingResolver $pricing) {}

    /**
     * Applique le tarif contrat aux lignes d'une WO : agreed_unit_price (grille →
     * sinon remise sur unit_price) + recalcul line_total. No-op sans contrat.
     * Idempotent : REMPLACE agreed_unit_price / line_total (n'incrémente pas).
     */
    public function priceLines(EnterpriseWorkOrder $workOrder): void
    {
        $contract = $workOrder->organizationContract;
        if (! $contract) {
            return;
        }

        foreach ($workOrder->lines as $line) {
            /** @var WorkOrderLine $line */
            $serviceId = $line->service_catalog_id ?: $workOrder->service_catalog_id;
            $base = (float) ($line->unit_price ?? 0);
            $res = $this->pricing->resolveAmount($contract, $serviceId ? (int) $serviceId : null, $base);

            $agreed = $res['label'] !== null ? $res['amount'] : $base;
            $qty = (float) ($line->quantity ?? 1);

            $line->forceFill([
                'agreed_unit_price' => $agreed,
                'line_total' => round($agreed * $qty, 2),
            ])->save();
        }
    }

    /**
     * Gate d'approbation contractuel : lève ContractPolicyException si le contrat
     * exige un bon de commande (requires_purchase_order) et que la WO n'en porte
     * pas. No-op sans contrat ou si le PO n'est pas requis.
     */
    public function assertApprovable(EnterpriseWorkOrder $workOrder): void
    {
        $contract = $workOrder->organizationContract;
        if (! $contract) {
            return;
        }

        if ($contract->requires_purchase_order && blank($workOrder->purchase_order_number)) {
            throw new ContractPolicyException('Un numéro de bon de commande (PO) est requis par le contrat avant approbation de cet ordre de service.');
        }
    }
}

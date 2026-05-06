<?php

namespace App\Services\Enterprise;

use App\Models\OrganizationSite;
use App\Models\User;
use Illuminate\Support\Arr;

class EnterpriseBookingPolicyResolver
{
    public function resolve(User $client, ?OrganizationSite $site = null): array
    {
        $account = $client->organizationAccount;
        $accountPolicy = $account && method_exists($account, 'bookingPolicy') ? $account->bookingPolicy() : [];
        $sitePolicy = $site && method_exists($site, 'bookingPolicy') ? $site->bookingPolicy() : [];

        $approvalMode = (string) ($sitePolicy['approval_mode'] ?? 'inherit');
        if ($approvalMode === 'inherit') {
            $approvalMode = (string) ($accountPolicy['approval_mode'] ?? 'auto');
        }

        $purchaseOrderRequired = Arr::get($sitePolicy, 'purchase_order_required');
        if ($purchaseOrderRequired === null) {
            $purchaseOrderRequired = (bool) ($accountPolicy['purchase_order_required'] ?? false);
        }

        $defaultCostCenter = Arr::get($sitePolicy, 'default_cost_center');
        if (! filled($defaultCostCenter)) {
            $defaultCostCenter = $accountPolicy['default_cost_center'] ?? null;
        }

        return [
            'approval_mode' => $approvalMode ?: 'auto',
            'approval_required' => $approvalMode === 'manual',
            'purchase_order_required' => (bool) $purchaseOrderRequired,
            'default_cost_center' => $defaultCostCenter,
            'negotiated_discount_percent' => filled($accountPolicy['negotiated_discount_percent'] ?? null)
                ? (float) $accountPolicy['negotiated_discount_percent']
                : null,
            'contract_reference' => $accountPolicy['contract_reference'] ?? null,
            'pricing_profile' => $accountPolicy['pricing_profile'] ?? null,
            'sla_hours' => filled($accountPolicy['sla_hours'] ?? null) ? (float) $accountPolicy['sla_hours'] : null,
            'priority_zone_id' => filled($accountPolicy['priority_zone_id'] ?? null) ? (int) $accountPolicy['priority_zone_id'] : null,
        ];
    }
}

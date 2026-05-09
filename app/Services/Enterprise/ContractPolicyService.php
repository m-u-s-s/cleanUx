<?php

namespace App\Services\Enterprise;

use App\Models\OrganizationAccount;
use App\Models\Booking;

class ContractPolicyService
{
    public function validateBooking(Booking $rdv, OrganizationAccount $org): array
    {
        $contract = $org->activeOrganizationContract;

        if (! $contract) {
            return ['valid' => true];
        }

        // Vérifier service autorisé
        if ($contract->services_allowed) {
            if (! in_array($rdv->service_catalog_id, $contract->services_allowed)) {
                return [
                    'valid' => false,
                    'message' => 'Service non autorisé par le contrat',
                ];
            }
        }

        // Vérifier horaires
        if ($contract->schedule_rules) {
            $day = strtolower($rdv->date->format('l'));
            $allowed = $contract->schedule_rules[$day] ?? null;

            if ($allowed) {
                if ($rdv->heure < $allowed['start'] || $rdv->heure > $allowed['end']) {
                    return [
                        'valid' => false,
                        'message' => 'Horaire hors contrat',
                    ];
                }
            }
        }

        return ['valid' => true];
    }

    public function applyDiscount(Booking $rdv, OrganizationAccount $org): void
    {
        $contract = $org->activeOrganizationContract;

        if (! $contract || $contract->discount_percent <= 0) {
            return;
        }

        $discount = $rdv->devis_estime * ($contract->discount_percent / 100);

        $rdv->update([
            'devis_estime' => round($rdv->devis_estime - $discount, 2),
        ]);
    }
}
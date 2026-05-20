<?php

namespace App\Services\FleetV2;

use App\Models\FleetVehicle;
use Carbon\Carbon;

class MaintenanceScheduler
{
    /**
     * Calcule la prochaine date d'entretien preventive selon type de véhicule.
     */
    public function computeNextDue(FleetVehicle $vehicle, ?Carbon $from = null): Carbon
    {
        $from ??= now();
        $intervals = (array) config('fleet_v2.default_maintenance_interval_days', []);
        $days = (int) ($intervals[$vehicle->vehicle_type] ?? 365);
        return $from->copy()->addDays($days);
    }

    /**
     * Vérifie si la maintenance préventive est en retard sur ce véhicule.
     */
    public function isOverdue(FleetVehicle $vehicle): bool
    {
        $lastLog = $vehicle->maintenanceLogs()
            ->where('maintenance_type', 'preventive')
            ->orderByDesc('performed_at')
            ->first();
        if (! $lastLog || ! $lastLog->next_due_at) {
            // jamais d'entretien → considéré overdue après 6 mois depuis registered_at
            $registered = $vehicle->registered_at;
            return $registered && $registered->copy()->addDays(180)->isPast();
        }
        return $lastLog->next_due_at->isPast();
    }
}

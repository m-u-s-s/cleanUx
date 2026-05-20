<?php

namespace App\Services\FleetV2;

use App\Models\FleetAssignment;
use App\Models\FleetCertification;
use App\Models\FleetEquipment;
use App\Models\FleetVehicle;
use Carbon\Carbon;

class AvailabilityChecker
{
    /**
     * Check si véhicule peut être assigné maintenant pour la fenêtre [from, until].
     */
    public function isVehicleAvailable(FleetVehicle $vehicle, ?Carbon $from = null, ?Carbon $until = null): bool
    {
        if (! $vehicle->isAvailable()) {
            return false;
        }
        if ((bool) config('fleet_v2.block_assignment_on_expired_cert', true)) {
            if ($vehicle->isExpired()) {
                return false;
            }
            // Block si une cert obligatoire véhicule est expired
            $expiredCerts = FleetCertification::query()
                ->forSubject(FleetCertification::SUBJECT_VEHICLE, $vehicle->id)
                ->where('status', FleetCertification::STATUS_EXPIRED)
                ->exists();
            if ($expiredCerts) {
                return false;
            }
        }
        return ! $this->hasConflictingAssignment('vehicle', $vehicle->id, $from, $until);
    }

    public function isEquipmentAvailable(FleetEquipment $equipment, ?Carbon $from = null, ?Carbon $until = null): bool
    {
        if (! $equipment->isAvailable()) {
            return false;
        }
        return ! $this->hasConflictingAssignment('equipment', $equipment->id, $from, $until);
    }

    /**
     * Trouve les véhicules disponibles pour des critères.
     * @return \Illuminate\Support\Collection<int, FleetVehicle>
     */
    public function findAvailableVehicles(array $criteria = []): \Illuminate\Support\Collection
    {
        $q = FleetVehicle::query()->available();
        if (! empty($criteria['vehicle_type'])) {
            $q->where('vehicle_type', $criteria['vehicle_type']);
        }
        if (! empty($criteria['min_capacity_kg'])) {
            $q->where('capacity_kg', '>=', (int) $criteria['min_capacity_kg']);
        }
        if (! empty($criteria['min_capacity_volume_m3'])) {
            $q->where('capacity_volume_m3', '>=', (float) $criteria['min_capacity_volume_m3']);
        }
        if (! empty($criteria['fuel_type'])) {
            $q->where('fuel_type', $criteria['fuel_type']);
        }

        $vehicles = $q->orderBy('plate')->get();

        if ((bool) config('fleet_v2.block_assignment_on_expired_cert', true)) {
            $vehicles = $vehicles->reject(fn ($v) => $v->isExpired());
        }
        return $vehicles->values();
    }

    public function findAvailableEquipment(array $criteria = []): \Illuminate\Support\Collection
    {
        $q = FleetEquipment::query()->available();
        if (! empty($criteria['equipment_type'])) {
            $q->where('equipment_type', $criteria['equipment_type']);
        }
        if (! empty($criteria['category'])) {
            $q->where('category', $criteria['category']);
        }
        return $q->orderBy('name')->get();
    }

    /**
     * Conflit = assignment active sur même véhicule/équipement qui chevauche la fenêtre demandée.
     */
    protected function hasConflictingAssignment(string $subjectType, int $subjectId, ?Carbon $from, ?Carbon $until): bool
    {
        $q = FleetAssignment::query()->active();
        if ($subjectType === 'vehicle') {
            $q->where('vehicle_id', $subjectId);
        } else {
            $q->where('equipment_id', $subjectId);
        }

        // Si aucune fenêtre donnée, conflict = présence d'au moins une assignment active
        if (! $from && ! $until) {
            return $q->exists();
        }

        $from ??= now();
        $until ??= $from->copy()->addYears(10);

        return $q->where(function ($w) use ($from, $until) {
            $w->where('assigned_at', '<=', $until)
                ->where(function ($w2) use ($from) {
                    $w2->whereNull('expected_return_at')
                        ->orWhere('expected_return_at', '>=', $from);
                });
        })->exists();
    }
}

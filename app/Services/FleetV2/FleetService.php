<?php

namespace App\Services\FleetV2;

use App\Models\FleetAssignment;
use App\Models\FleetEquipment;
use App\Models\FleetMaintenanceLog;
use App\Models\FleetVehicle;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class FleetService
{
    public function __construct(
        protected AvailabilityChecker $availability,
        protected MaintenanceScheduler $scheduler,
    ) {}

    /**
     * Assigne un véhicule à un provider pour une fenêtre temporelle.
     * Idempotent : si une assignment active existe déjà sur ce véhicule pour ce provider+booking, retournée.
     */
    public function assignVehicle(
        FleetVehicle $vehicle,
        User $provider,
        ?int $bookingId = null,
        ?Carbon $assignedAt = null,
        ?Carbon $expectedReturnAt = null,
        ?User $assignedBy = null,
    ): FleetAssignment {
        // Idempotency lookup AVANT availability check : un retry sur (vehicle, provider, booking)
        // doit retourner l'assignment existante même si le vehicle est déjà in_use à cause de cette assign.
        $existing = FleetAssignment::query()
            ->active()
            ->where('vehicle_id', $vehicle->id)
            ->where('provider_user_id', $provider->id)
            ->when($bookingId, fn ($q) => $q->where('booking_id', $bookingId))
            ->first();
        if ($existing) {
            return $existing;
        }

        if (! $this->availability->isVehicleAvailable($vehicle, $assignedAt, $expectedReturnAt)) {
            throw ValidationException::withMessages([
                'vehicle' => ['Véhicule indisponible pour la fenêtre demandée.'],
            ]);
        }

        return DB::transaction(function () use ($vehicle, $provider, $bookingId, $assignedAt, $expectedReturnAt, $assignedBy) {
            $a = FleetAssignment::query()->create([
                'code' => FleetAssignment::generateCode(),
                'vehicle_id' => $vehicle->id,
                'booking_id' => $bookingId,
                'provider_user_id' => $provider->id,
                'status' => FleetAssignment::STATUS_ACTIVE,
                'assigned_at' => $assignedAt ?: now(),
                'expected_return_at' => $expectedReturnAt,
                'start_odometer_km' => $vehicle->odometer_km,
                'assigned_by_user_id' => $assignedBy?->id,
            ]);

            if ((bool) config('fleet_v2.auto_update_status_on_assign', true)) {
                $vehicle->update([
                    'status' => FleetVehicle::STATUS_IN_USE,
                    'current_provider_id' => $provider->id,
                ]);
            }
            return $a;
        });
    }

    public function assignEquipment(
        FleetEquipment $equipment,
        User $provider,
        ?int $bookingId = null,
        ?Carbon $assignedAt = null,
        ?Carbon $expectedReturnAt = null,
        ?User $assignedBy = null,
    ): FleetAssignment {
        // Idempotency d'abord (cf. assignVehicle).
        $existing = FleetAssignment::query()
            ->active()
            ->where('equipment_id', $equipment->id)
            ->where('provider_user_id', $provider->id)
            ->when($bookingId, fn ($q) => $q->where('booking_id', $bookingId))
            ->first();
        if ($existing) {
            return $existing;
        }

        if (! $this->availability->isEquipmentAvailable($equipment, $assignedAt, $expectedReturnAt)) {
            throw ValidationException::withMessages([
                'equipment' => ['Équipement indisponible.'],
            ]);
        }

        return DB::transaction(function () use ($equipment, $provider, $bookingId, $assignedAt, $expectedReturnAt, $assignedBy) {
            $a = FleetAssignment::query()->create([
                'code' => FleetAssignment::generateCode(),
                'equipment_id' => $equipment->id,
                'booking_id' => $bookingId,
                'provider_user_id' => $provider->id,
                'status' => FleetAssignment::STATUS_ACTIVE,
                'assigned_at' => $assignedAt ?: now(),
                'expected_return_at' => $expectedReturnAt,
                'assigned_by_user_id' => $assignedBy?->id,
            ]);

            if ((bool) config('fleet_v2.auto_update_status_on_assign', true)) {
                $equipment->update([
                    'status' => FleetEquipment::STATUS_IN_USE,
                    'current_provider_id' => $provider->id,
                ]);
            }
            return $a;
        });
    }

    /**
     * Termine une assignment. Met à jour le statut du véhicule/équipement selon condition.
     */
    public function returnAssignment(
        FleetAssignment $assignment,
        string $condition = FleetAssignment::CONDITION_OK,
        ?string $notes = null,
        ?int $endOdometer = null,
    ): FleetAssignment {
        $allowedConditions = (array) config('fleet_v2.damage_conditions', []);
        if (! in_array($condition, $allowedConditions, true)) {
            throw ValidationException::withMessages(['condition' => ['Condition invalide.']]);
        }
        if (! $assignment->isActive()) {
            throw ValidationException::withMessages(['assignment' => ['Assignment déjà terminée.']]);
        }

        return DB::transaction(function () use ($assignment, $condition, $notes, $endOdometer) {
            $assignment->update([
                'status' => FleetAssignment::STATUS_COMPLETED,
                'returned_at' => now(),
                'returned_condition' => $condition,
                'damage_notes' => $notes,
                'end_odometer_km' => $endOdometer,
            ]);

            // Update odometer du véhicule
            if ($assignment->isForVehicle() && $endOdometer && $assignment->vehicle) {
                $assignment->vehicle->update(['odometer_km' => $endOdometer]);
            }

            // Update statut sujet selon condition
            $this->updateSubjectStatusAfterReturn($assignment, $condition);

            // Auto-schedule maintenance si damaged/needs_maintenance
            if ((bool) config('fleet_v2.auto_schedule_maintenance_on_damage', true)
                && in_array($condition, [FleetAssignment::CONDITION_DAMAGED, FleetAssignment::CONDITION_NEEDS_MAINTENANCE], true)
            ) {
                $this->scheduleMaintenance($assignment, $notes);
            }

            return $assignment->fresh();
        });
    }

    protected function updateSubjectStatusAfterReturn(FleetAssignment $assignment, string $condition): void
    {
        $statusMap = [
            FleetAssignment::CONDITION_OK => FleetVehicle::STATUS_AVAILABLE,
            FleetAssignment::CONDITION_DAMAGED => FleetVehicle::STATUS_MAINTENANCE,
            FleetAssignment::CONDITION_NEEDS_MAINTENANCE => FleetVehicle::STATUS_MAINTENANCE,
            FleetAssignment::CONDITION_LOST => FleetVehicle::STATUS_RETIRED,
        ];
        $newStatus = $statusMap[$condition] ?? FleetVehicle::STATUS_AVAILABLE;

        if ($assignment->isForVehicle() && $assignment->vehicle) {
            $assignment->vehicle->update([
                'status' => $newStatus,
                'current_provider_id' => null,
            ]);
        }
        if ($assignment->equipment_id && $assignment->equipment) {
            $equipStatus = $condition === FleetAssignment::CONDITION_LOST
                ? FleetEquipment::STATUS_LOST
                : ($condition === FleetAssignment::CONDITION_OK
                    ? FleetEquipment::STATUS_AVAILABLE
                    : FleetEquipment::STATUS_MAINTENANCE);
            $assignment->equipment->update([
                'status' => $equipStatus,
                'current_provider_id' => null,
            ]);
        }
    }

    /**
     * Schedule une maintenance corrective sur le véhicule/équipement d'une assignment.
     */
    public function scheduleMaintenance(FleetAssignment $assignment, ?string $notes = null, ?int $costCents = null): FleetMaintenanceLog
    {
        return FleetMaintenanceLog::query()->create([
            'vehicle_id' => $assignment->vehicle_id,
            'equipment_id' => $assignment->equipment_id,
            'maintenance_type' => FleetMaintenanceLog::TYPE_CORRECTIVE,
            'performed_at' => now(),
            'cost_cents' => $costCents,
            'notes' => $notes ?: 'Auto-scheduled after assignment return with damage',
            'next_due_at' => null,
        ]);
    }

    /**
     * Log explicite d'une maintenance (manuelle, par admin).
     */
    public function logMaintenance(
        ?FleetVehicle $vehicle,
        ?FleetEquipment $equipment,
        string $maintenanceType,
        ?Carbon $performedAt = null,
        ?int $costCents = null,
        ?string $notes = null,
        ?User $performedBy = null,
        ?int $odometerAtServiceKm = null,
    ): FleetMaintenanceLog {
        if (! $vehicle && ! $equipment) {
            throw ValidationException::withMessages(['subject' => ['Vehicle ou Equipment requis.']]);
        }
        $allowed = (array) config('fleet_v2.maintenance_types', []);
        if (! in_array($maintenanceType, $allowed, true)) {
            throw ValidationException::withMessages(['maintenance_type' => ['Type invalide.']]);
        }
        $nextDue = $vehicle
            ? $this->scheduler->computeNextDue($vehicle, $performedAt ?: now())
            : null;

        return FleetMaintenanceLog::query()->create([
            'vehicle_id' => $vehicle?->id,
            'equipment_id' => $equipment?->id,
            'maintenance_type' => $maintenanceType,
            'performed_at' => $performedAt ?: now(),
            'performed_by_user_id' => $performedBy?->id,
            'cost_cents' => $costCents,
            'notes' => $notes,
            'next_due_at' => $nextDue,
            'odometer_at_service_km' => $odometerAtServiceKm,
        ]);
    }
}

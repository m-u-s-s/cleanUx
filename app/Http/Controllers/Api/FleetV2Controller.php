<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\FleetAssignment;
use App\Models\FleetCertification;
use App\Models\FleetEquipment;
use App\Models\FleetMaintenanceLog;
use App\Models\FleetVehicle;
use App\Models\User;
use App\Services\FleetV2\AvailabilityChecker;
use App\Services\FleetV2\CertificationExpiryScanner;
use App\Services\FleetV2\FleetService;
use Carbon\Carbon;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;

class FleetV2Controller extends Controller
{
    public function __construct(
        protected FleetService $fleet,
        protected AvailabilityChecker $availability,
        protected CertificationExpiryScanner $scanner,
    ) {}

    /* ---- USER (provider) ---- */

    public function listMyAssignments(Request $request): JsonResponse
    {
        $rows = FleetAssignment::query()
            ->where('provider_user_id', $request->user()->id)
            ->with(['vehicle:id,code,plate,brand,model', 'equipment:id,code,name'])
            ->orderByDesc('assigned_at')
            ->limit((int) $request->integer('limit', 50))
            ->get();
        return response()->json(['data' => $rows]);
    }

    public function returnAssignment(Request $request, FleetAssignment $assignment): JsonResponse
    {
        if ($assignment->provider_user_id !== $request->user()->id) {
            return response()->json(['ok' => false, 'error' => 'forbidden'], 403);
        }
        $data = $request->validate([
            'condition' => ['required', 'string', 'in:ok,damaged,lost,needs_maintenance'],
            'notes' => ['nullable', 'string', 'max:2000'],
            'end_odometer_km' => ['nullable', 'integer', 'min:0'],
        ]);
        try {
            $row = $this->fleet->returnAssignment(
                $assignment,
                $data['condition'],
                $data['notes'] ?? null,
                $data['end_odometer_km'] ?? null,
            );
        } catch (ValidationException $e) {
            return response()->json(['ok' => false, 'errors' => $e->errors()], 422);
        }
        return response()->json(['ok' => true, 'assignment' => $row]);
    }

    public function findAvailable(Request $request): JsonResponse
    {
        $type = $request->string('type', 'vehicle')->toString();
        $criteria = $request->only([
            'vehicle_type', 'fuel_type', 'min_capacity_kg', 'min_capacity_volume_m3',
            'equipment_type', 'category',
        ]);

        $rows = $type === 'equipment'
            ? $this->availability->findAvailableEquipment($criteria)
            : $this->availability->findAvailableVehicles($criteria);

        return response()->json(['data' => $rows->values()->all(), 'type' => $type]);
    }

    /* ---- ADMIN ---- */

    public function adminListVehicles(Request $request): JsonResponse
    {
        $rows = FleetVehicle::query()
            ->when($request->filled('status'), fn ($q) => $q->where('status', $request->string('status')))
            ->when($request->filled('vehicle_type'), fn ($q) => $q->where('vehicle_type', $request->string('vehicle_type')))
            ->orderBy('plate')
            ->limit((int) $request->integer('limit', 100))
            ->get();
        return response()->json(['data' => $rows]);
    }

    public function adminCreateVehicle(Request $request): JsonResponse
    {
        $allowedTypes = (array) config('fleet_v2.vehicle_types', []);
        $allowedFuels = (array) config('fleet_v2.fuel_types', []);
        $data = $request->validate([
            'plate' => ['required', 'string', 'max:24', 'unique:fleet_vehicles,plate'],
            'brand' => ['nullable', 'string', 'max:64'],
            'model' => ['nullable', 'string', 'max:64'],
            'year' => ['nullable', 'integer', 'min:1900', 'max:2100'],
            'vehicle_type' => ['required', 'string', 'in:' . implode(',', $allowedTypes)],
            'fuel_type' => ['nullable', 'string', 'in:' . implode(',', $allowedFuels)],
            'capacity_kg' => ['nullable', 'integer', 'min:0'],
            'capacity_volume_m3' => ['nullable', 'numeric', 'min:0'],
            'registered_country' => ['nullable', 'string', 'size:2'],
            'registered_at' => ['nullable', 'date'],
            'insurance_expires_at' => ['nullable', 'date'],
            'control_technique_expires_at' => ['nullable', 'date'],
            'odometer_km' => ['nullable', 'integer', 'min:0'],
        ]);

        $vehicle = FleetVehicle::query()->create(array_merge($data, [
            'code' => FleetVehicle::generateCode(),
            'status' => FleetVehicle::STATUS_AVAILABLE,
        ]));
        return response()->json(['ok' => true, 'vehicle' => $vehicle], 201);
    }

    public function adminCreateEquipment(Request $request): JsonResponse
    {
        $allowedTypes = (array) config('fleet_v2.equipment_types', []);
        $allowedCategories = (array) config('fleet_v2.equipment_categories', []);
        $data = $request->validate([
            'name' => ['required', 'string', 'max:191'],
            'equipment_type' => ['required', 'string', 'in:' . implode(',', $allowedTypes)],
            'category' => ['nullable', 'string', 'in:' . implode(',', $allowedCategories)],
            'serial_number' => ['nullable', 'string', 'max:64'],
            'brand' => ['nullable', 'string', 'max:64'],
            'model' => ['nullable', 'string', 'max:64'],
            'value_cents' => ['nullable', 'integer', 'min:0'],
            'currency' => ['nullable', 'string', 'size:3'],
            'purchased_at' => ['nullable', 'date'],
            'warranty_expires_at' => ['nullable', 'date'],
        ]);
        $equipment = FleetEquipment::query()->create(array_merge($data, [
            'code' => FleetEquipment::generateCode(),
            'status' => FleetEquipment::STATUS_AVAILABLE,
        ]));
        return response()->json(['ok' => true, 'equipment' => $equipment], 201);
    }

    public function adminListEquipment(Request $request): JsonResponse
    {
        $rows = FleetEquipment::query()
            ->when($request->filled('status'), fn ($q) => $q->where('status', $request->string('status')))
            ->when($request->filled('category'), fn ($q) => $q->where('category', $request->string('category')))
            ->orderBy('name')
            ->limit((int) $request->integer('limit', 100))
            ->get();
        return response()->json(['data' => $rows]);
    }

    public function adminAssignVehicle(Request $request, FleetVehicle $vehicle): JsonResponse
    {
        $data = $request->validate([
            'provider_user_id' => ['required', 'integer', 'exists:users,id'],
            'booking_id' => ['nullable', 'integer'],
            'assigned_at' => ['nullable', 'date'],
            'expected_return_at' => ['nullable', 'date'],
        ]);
        $provider = User::query()->findOrFail($data['provider_user_id']);
        try {
            $assignment = $this->fleet->assignVehicle(
                $vehicle,
                $provider,
                $data['booking_id'] ?? null,
                isset($data['assigned_at']) ? Carbon::parse($data['assigned_at']) : null,
                isset($data['expected_return_at']) ? Carbon::parse($data['expected_return_at']) : null,
                $request->user(),
            );
        } catch (ValidationException $e) {
            return response()->json(['ok' => false, 'errors' => $e->errors()], 422);
        }
        return response()->json(['ok' => true, 'assignment' => $assignment], 201);
    }

    public function adminAssignEquipment(Request $request, FleetEquipment $equipment): JsonResponse
    {
        $data = $request->validate([
            'provider_user_id' => ['required', 'integer', 'exists:users,id'],
            'booking_id' => ['nullable', 'integer'],
            'assigned_at' => ['nullable', 'date'],
            'expected_return_at' => ['nullable', 'date'],
        ]);
        $provider = User::query()->findOrFail($data['provider_user_id']);
        try {
            $assignment = $this->fleet->assignEquipment(
                $equipment,
                $provider,
                $data['booking_id'] ?? null,
                isset($data['assigned_at']) ? Carbon::parse($data['assigned_at']) : null,
                isset($data['expected_return_at']) ? Carbon::parse($data['expected_return_at']) : null,
                $request->user(),
            );
        } catch (ValidationException $e) {
            return response()->json(['ok' => false, 'errors' => $e->errors()], 422);
        }
        return response()->json(['ok' => true, 'assignment' => $assignment], 201);
    }

    public function adminListAssignments(Request $request): JsonResponse
    {
        $rows = FleetAssignment::query()
            ->with(['vehicle:id,code,plate', 'equipment:id,code,name', 'provider:id,email,name'])
            ->when($request->filled('status'), fn ($q) => $q->where('status', $request->string('status')))
            ->when($request->filled('provider_user_id'), fn ($q) => $q->where('provider_user_id', $request->integer('provider_user_id')))
            ->orderByDesc('assigned_at')
            ->limit((int) $request->integer('limit', 100))
            ->get();
        return response()->json(['data' => $rows]);
    }

    public function adminLogMaintenance(Request $request): JsonResponse
    {
        $data = $request->validate([
            'vehicle_id' => ['nullable', 'integer', 'exists:fleet_vehicles,id'],
            'equipment_id' => ['nullable', 'integer', 'exists:fleet_equipment,id'],
            'maintenance_type' => ['required', 'string', 'in:preventive,corrective,inspection'],
            'performed_at' => ['nullable', 'date'],
            'cost_cents' => ['nullable', 'integer', 'min:0'],
            'notes' => ['nullable', 'string', 'max:2000'],
            'odometer_at_service_km' => ['nullable', 'integer', 'min:0'],
        ]);

        $vehicle = isset($data['vehicle_id']) ? FleetVehicle::query()->find($data['vehicle_id']) : null;
        $equipment = isset($data['equipment_id']) ? FleetEquipment::query()->find($data['equipment_id']) : null;
        try {
            $row = $this->fleet->logMaintenance(
                $vehicle,
                $equipment,
                $data['maintenance_type'],
                isset($data['performed_at']) ? Carbon::parse($data['performed_at']) : null,
                $data['cost_cents'] ?? null,
                $data['notes'] ?? null,
                $request->user(),
                $data['odometer_at_service_km'] ?? null,
            );
        } catch (ValidationException $e) {
            return response()->json(['ok' => false, 'errors' => $e->errors()], 422);
        }
        return response()->json(['ok' => true, 'log' => $row], 201);
    }

    public function adminListMaintenanceLogs(Request $request): JsonResponse
    {
        $rows = FleetMaintenanceLog::query()
            ->with(['vehicle:id,code,plate', 'equipment:id,code,name'])
            ->orderByDesc('performed_at')
            ->limit((int) $request->integer('limit', 100))
            ->get();
        return response()->json(['data' => $rows]);
    }

    public function adminAddCertification(Request $request): JsonResponse
    {
        $allowedTypes = (array) config('fleet_v2.certification_types', []);
        $data = $request->validate([
            'subject_type' => ['required', 'string', 'in:vehicle,equipment,provider'],
            'subject_id' => ['required', 'integer'],
            'certification_type' => ['required', 'string', 'in:' . implode(',', $allowedTypes)],
            'reference' => ['nullable', 'string', 'max:191'],
            'issued_at' => ['nullable', 'date'],
            'expires_at' => ['nullable', 'date'],
            'issuing_authority' => ['nullable', 'string', 'max:191'],
        ]);
        $cert = FleetCertification::query()->create(array_merge($data, [
            'status' => FleetCertification::STATUS_ACTIVE,
            'created_by_user_id' => $request->user()->id,
        ]));
        $this->scanner->scanAndUpdate();
        return response()->json(['ok' => true, 'certification' => $cert->fresh()], 201);
    }

    public function adminListCertifications(Request $request): JsonResponse
    {
        $rows = FleetCertification::query()
            ->when($request->filled('subject_type'), fn ($q) => $q->where('subject_type', $request->string('subject_type')))
            ->when($request->filled('status'), fn ($q) => $q->where('status', $request->string('status')))
            ->orderBy('expires_at')
            ->limit((int) $request->integer('limit', 100))
            ->get();
        return response()->json(['data' => $rows]);
    }

    public function adminScanExpiring(): JsonResponse
    {
        $counts = $this->scanner->scanAndUpdate();
        return response()->json(['ok' => true, 'counts' => $counts]);
    }
}

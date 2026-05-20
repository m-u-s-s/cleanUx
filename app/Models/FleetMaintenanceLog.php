<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class FleetMaintenanceLog extends Model
{
    public const TYPE_PREVENTIVE = 'preventive';
    public const TYPE_CORRECTIVE = 'corrective';
    public const TYPE_INSPECTION = 'inspection';

    protected $fillable = [
        'vehicle_id', 'equipment_id',
        'maintenance_type', 'performed_at',
        'performed_by_user_id', 'provider_name',
        'cost_cents', 'currency',
        'next_due_at', 'odometer_at_service_km',
        'notes', 'metadata',
    ];

    protected $casts = [
        'performed_at' => 'datetime',
        'next_due_at' => 'datetime',
        'cost_cents' => 'integer',
        'odometer_at_service_km' => 'integer',
        'metadata' => 'array',
    ];

    public function vehicle(): BelongsTo
    {
        return $this->belongsTo(FleetVehicle::class, 'vehicle_id');
    }

    public function equipment(): BelongsTo
    {
        return $this->belongsTo(FleetEquipment::class, 'equipment_id');
    }

    public function scopeOverdue(Builder $q): Builder
    {
        return $q->whereNotNull('next_due_at')->where('next_due_at', '<', now());
    }
}

<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Str;

class FleetVehicle extends Model
{
    public const STATUS_AVAILABLE = 'available';
    public const STATUS_IN_USE = 'in_use';
    public const STATUS_MAINTENANCE = 'maintenance';
    public const STATUS_RETIRED = 'retired';

    protected $fillable = [
        'code', 'plate', 'brand', 'model', 'year',
        'vehicle_type', 'fuel_type',
        'capacity_kg', 'capacity_volume_m3',
        'status', 'current_provider_id',
        'current_location', 'last_seen_at',
        'registered_country', 'registered_at',
        'insurance_expires_at', 'control_technique_expires_at',
        'odometer_km', 'metadata',
    ];

    protected $casts = [
        'current_location' => 'array',
        'last_seen_at' => 'datetime',
        'registered_at' => 'date',
        'insurance_expires_at' => 'date',
        'control_technique_expires_at' => 'date',
        'capacity_volume_m3' => 'float',
        'metadata' => 'array',
    ];

    public static function generateCode(): string
    {
        return 'veh_' . Str::lower(Str::random(20));
    }

    public function currentProvider(): BelongsTo
    {
        return $this->belongsTo(User::class, 'current_provider_id');
    }

    public function assignments(): HasMany
    {
        return $this->hasMany(FleetAssignment::class, 'vehicle_id');
    }

    public function maintenanceLogs(): HasMany
    {
        return $this->hasMany(FleetMaintenanceLog::class, 'vehicle_id');
    }

    public function scopeAvailable(Builder $q): Builder
    {
        return $q->where('status', self::STATUS_AVAILABLE);
    }

    public function isAvailable(): bool
    {
        return $this->status === self::STATUS_AVAILABLE;
    }

    public function isExpired(): bool
    {
        return ($this->insurance_expires_at && $this->insurance_expires_at->isPast())
            || ($this->control_technique_expires_at && $this->control_technique_expires_at->isPast());
    }
}

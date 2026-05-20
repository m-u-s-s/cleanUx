<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Str;

class FleetEquipment extends Model
{
    public const STATUS_AVAILABLE = 'available';
    public const STATUS_IN_USE = 'in_use';
    public const STATUS_MAINTENANCE = 'maintenance';
    public const STATUS_RETIRED = 'retired';
    public const STATUS_LOST = 'lost';

    protected $table = 'fleet_equipment';

    protected $fillable = [
        'code', 'name', 'equipment_type', 'category',
        'serial_number', 'brand', 'model', 'status',
        'current_provider_id', 'value_cents', 'currency',
        'purchased_at', 'warranty_expires_at',
        'current_location', 'metadata',
    ];

    protected $casts = [
        'purchased_at' => 'date',
        'warranty_expires_at' => 'date',
        'current_location' => 'array',
        'value_cents' => 'integer',
        'metadata' => 'array',
    ];

    public static function generateCode(): string
    {
        return 'eqp_' . Str::lower(Str::random(20));
    }

    public function currentProvider(): BelongsTo
    {
        return $this->belongsTo(User::class, 'current_provider_id');
    }

    public function assignments(): HasMany
    {
        return $this->hasMany(FleetAssignment::class, 'equipment_id');
    }

    public function maintenanceLogs(): HasMany
    {
        return $this->hasMany(FleetMaintenanceLog::class, 'equipment_id');
    }

    public function scopeAvailable(Builder $q): Builder
    {
        return $q->where('status', self::STATUS_AVAILABLE);
    }

    public function isAvailable(): bool
    {
        return $this->status === self::STATUS_AVAILABLE;
    }
}

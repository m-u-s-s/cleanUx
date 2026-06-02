<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;
use Illuminate\Support\Str;

/**
 * @property ?Carbon $assigned_at
 * @property ?Carbon $expected_return_at
 * @property ?Carbon $returned_at
 * @property array $metadata
 */
class FleetAssignment extends Model
{
    public const STATUS_ACTIVE = 'active';

    public const STATUS_COMPLETED = 'completed';

    public const STATUS_CANCELLED = 'cancelled';

    public const CONDITION_OK = 'ok';

    public const CONDITION_DAMAGED = 'damaged';

    public const CONDITION_LOST = 'lost';

    public const CONDITION_NEEDS_MAINTENANCE = 'needs_maintenance';

    protected $fillable = [
        'code', 'vehicle_id', 'equipment_id', 'booking_id',
        'provider_user_id', 'status',
        'assigned_at', 'expected_return_at', 'returned_at',
        'returned_condition', 'damage_notes',
        'start_odometer_km', 'end_odometer_km',
        'assigned_by_user_id', 'metadata',
    ];

    protected $casts = [
        'assigned_at' => 'datetime',
        'expected_return_at' => 'datetime',
        'returned_at' => 'datetime',
        'metadata' => 'array',
    ];

    public static function generateCode(): string
    {
        return 'fa_'.Str::lower(Str::random(20));
    }

    /** @return BelongsTo<FleetVehicle, $this> */
    public function vehicle(): BelongsTo
    {
        return $this->belongsTo(FleetVehicle::class, 'vehicle_id');
    }

    /** @return BelongsTo<FleetEquipment, $this> */
    public function equipment(): BelongsTo
    {
        return $this->belongsTo(FleetEquipment::class, 'equipment_id');
    }

    /** @return BelongsTo<User, $this> */
    public function provider(): BelongsTo
    {
        return $this->belongsTo(User::class, 'provider_user_id');
    }

    public function scopeActive(Builder $q): Builder
    {
        return $q->where('status', self::STATUS_ACTIVE);
    }

    public function isActive(): bool
    {
        return $this->status === self::STATUS_ACTIVE;
    }

    public function isOverdue(): bool
    {
        return $this->isActive()
            && $this->expected_return_at
            && $this->expected_return_at->isPast();
    }

    public function isForVehicle(): bool
    {
        return $this->vehicle_id !== null;
    }
}

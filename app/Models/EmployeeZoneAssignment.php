<?php

namespace App\Models;

use Database\Factories\EmployeeZoneAssignmentFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class EmployeeZoneAssignment extends Model
{
    /** @use HasFactory<EmployeeZoneAssignmentFactory> */
    use HasFactory;

    protected $fillable = [
        'user_id',
        'service_zone_id',
        'assignment_type',
        'coverage_priority',
        'is_active',
        'starts_at',
        'ends_at',
        'notes',
    ];

    protected $casts = [
        'is_active' => 'boolean',
        'starts_at' => 'datetime',
        'ends_at' => 'datetime',
    ];

    /** @return BelongsTo<User, $this> */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /** @return BelongsTo<ServiceZone, $this> */
    public function serviceZone(): BelongsTo
    {
        return $this->belongsTo(ServiceZone::class);
    }
}

<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class MissionTrackingSession extends Model
{
    use HasFactory;

    protected $fillable = [
        'mission_id',
        'assignment_id',
        'is_client_visible',
        'started_at',
        'ended_at',
        'start_lat',
        'start_lng',
        'point_count',
        'distance_meters',
        'meta',
        'user_id',
        'employee_user_id',
        'tracking_mode',
        'is_active',
        'last_lat',
        'last_lng',
    ];

    protected $casts = [
        'is_client_visible' => 'boolean',
        'is_active' => 'boolean',
        'started_at' => 'datetime',
        'ended_at' => 'datetime',
        'start_lat' => 'decimal:7',
        'start_lng' => 'decimal:7',
        'last_lat' => 'decimal:7',
        'last_lng' => 'decimal:7',
        'meta' => 'array',
    ];

    public function mission(): BelongsTo
    {
        return $this->belongsTo(Mission::class);
    }

    public function assignment(): BelongsTo
    {
        return $this->belongsTo(MissionAssignment::class, 'assignment_id');
    }

    public function employee(): BelongsTo
    {
        return $this->belongsTo(User::class, 'employee_user_id');
    }

    public function points(): HasMany
    {
        return $this->hasMany(MissionTrackingPoint::class, 'tracking_session_id');
    }

    protected static function booted(): void
    {
        static::creating(function (MissionTrackingSession $session) {
            if (blank($session->user_id) && filled($session->employee_user_id)) {
                $session->user_id = $session->employee_user_id;
            }

            if (blank($session->employee_user_id) && filled($session->user_id)) {
                $session->employee_user_id = $session->user_id;
            }

            if (blank($session->status)) {
                $session->status = ($session->is_active ?? false) ? 'active' : 'stopped';
            }
        });

        static::updating(function (MissionTrackingSession $session) {
            if (blank($session->user_id) && filled($session->employee_user_id)) {
                $session->user_id = $session->employee_user_id;
            }

            if (blank($session->employee_user_id) && filled($session->user_id)) {
                $session->employee_user_id = $session->user_id;
            }
        });
    }
}

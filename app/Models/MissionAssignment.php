<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class MissionAssignment extends Model
{
    use HasFactory;

    protected $fillable = [
        'mission_id',
        'user_id',
        'role',
        'role_on_mission',
        'status',
        'assignment_status',
        'assigned_at',
        'notification_sent_at',
        'expires_at',
        'accepted_at',
        'declined_at',
        'arrived_at',
        'completed_at',
        'response_seconds',
        'decline_reason',
        'escalated_from_assignment_id',
    ];

    protected $casts = [
        'assigned_at' => 'datetime',
        'notification_sent_at' => 'datetime',
        'expires_at' => 'datetime',
        'accepted_at' => 'datetime',
        'declined_at' => 'datetime',
        'arrived_at' => 'datetime',
        'completed_at' => 'datetime',
        'response_seconds' => 'integer',
    ];

    public function mission(): BelongsTo
    {
        return $this->belongsTo(Mission::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}

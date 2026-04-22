<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class MissionTeamAssignment extends Model
{
    use HasFactory;

    protected $fillable = [
        'mission_id',
        'field_team_id',
        'lead_assignment_id',
        'assignment_status',
        'assigned_at',
        'accepted_at',
        'started_at',
        'completed_at',
        'instructions_snapshot',
        'metadata',
    ];

    protected $casts = [
        'assigned_at' => 'datetime',
        'accepted_at' => 'datetime',
        'started_at' => 'datetime',
        'completed_at' => 'datetime',
        'instructions_snapshot' => 'array',
        'metadata' => 'array',
    ];

    public function mission(): BelongsTo
    {
        return $this->belongsTo(Mission::class);
    }

    public function fieldTeam(): BelongsTo
    {
        return $this->belongsTo(FieldTeam::class);
    }

    public function leadAssignment(): BelongsTo
    {
        return $this->belongsTo(MissionAssignment::class, 'lead_assignment_id');
    }
}

<?php

namespace App\Models;

use Database\Factories\MissionTeamAssignmentFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class MissionTeamAssignment extends Model
{
    /** @use HasFactory<MissionTeamAssignmentFactory> */
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

    /** @return BelongsTo<Mission, $this> */
    public function mission(): BelongsTo
    {
        return $this->belongsTo(Mission::class);
    }

    /** @return BelongsTo<FieldTeam, $this> */
    public function fieldTeam(): BelongsTo
    {
        return $this->belongsTo(FieldTeam::class);
    }

    /** @return BelongsTo<MissionAssignment, $this> */
    public function leadAssignment(): BelongsTo
    {
        return $this->belongsTo(MissionAssignment::class, 'lead_assignment_id');
    }
}

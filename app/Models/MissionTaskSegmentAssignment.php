<?php

namespace App\Models;

use Database\Factories\MissionTaskSegmentAssignmentFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class MissionTaskSegmentAssignment extends Model
{
    /** @use HasFactory<MissionTaskSegmentAssignmentFactory> */
    use HasFactory;

    protected $fillable = [
        'mission_task_segment_id',
        'mission_id',
        'field_team_id',
        'user_id',
        'assigned_by_user_id',
        'assignment_role',
        'status',
        'planned_minutes',
        'actual_minutes',
        'sequence_order',
        'notes',
        'started_at',
        'completed_at',
    ];

    protected $casts = [
        'planned_minutes' => 'integer',
        'actual_minutes' => 'integer',
        'sequence_order' => 'integer',
        'started_at' => 'datetime',
        'completed_at' => 'datetime',
    ];

    /** @return BelongsTo<MissionTaskSegment, $this> */
    public function segment(): BelongsTo
    {
        return $this->belongsTo(MissionTaskSegment::class, 'mission_task_segment_id');
    }

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

    /** @return BelongsTo<User, $this> */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /** @return BelongsTo<User, $this> */
    public function assignedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'assigned_by_user_id');
    }

    /** @return HasMany<MissionMemberStatus, $this> */
    public function memberStatuses(): HasMany
    {
        return $this->hasMany(MissionMemberStatus::class, 'segment_assignment_id');
    }
}

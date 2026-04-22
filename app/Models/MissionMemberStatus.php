<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class MissionMemberStatus extends Model
{
    use HasFactory;

    protected $fillable = [
        'mission_id',
        'mission_task_segment_id',
        'segment_assignment_id',
        'field_team_id',
        'user_id',
        'status',
        'readiness_status',
        'progress_percent',
        'minutes_spent',
        'is_blocked',
        'blocking_reason',
        'last_reported_at',
        'notes',
    ];

    protected $casts = [
        'progress_percent' => 'integer',
        'minutes_spent' => 'integer',
        'is_blocked' => 'boolean',
        'last_reported_at' => 'datetime',
    ];

    public function mission(): BelongsTo
    {
        return $this->belongsTo(Mission::class);
    }

    public function segment(): BelongsTo
    {
        return $this->belongsTo(MissionTaskSegment::class, 'mission_task_segment_id');
    }

    public function segmentAssignment(): BelongsTo
    {
        return $this->belongsTo(MissionTaskSegmentAssignment::class, 'segment_assignment_id');
    }

    public function fieldTeam(): BelongsTo
    {
        return $this->belongsTo(FieldTeam::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}

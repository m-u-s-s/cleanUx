<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class MissionReinforcementRequest extends Model
{
    use HasFactory;

    protected $fillable = [
        'mission_id',
        'mission_batch_id',
        'mission_batch_day_id',
        'mission_task_segment_id',
        'requested_by_user_id',
        'field_team_id',
        'service_partner_id',
        'status',
        'priority',
        'requested_members',
        'requested_minutes',
        'reason',
        'resolution_notes',
        'resolved_by_user_id',
        'resolved_at',
    ];

    protected $casts = [
        'requested_members' => 'integer',
        'requested_minutes' => 'integer',
        'resolved_at' => 'datetime',
    ];

    public function mission(): BelongsTo
    {
        return $this->belongsTo(Mission::class);
    }

    public function batch(): BelongsTo
    {
        return $this->belongsTo(MissionBatch::class, 'mission_batch_id');
    }

    public function batchDay(): BelongsTo
    {
        return $this->belongsTo(MissionBatchDay::class, 'mission_batch_day_id');
    }

    public function segment(): BelongsTo
    {
        return $this->belongsTo(MissionTaskSegment::class, 'mission_task_segment_id');
    }

    public function requester(): BelongsTo
    {
        return $this->belongsTo(User::class, 'requested_by_user_id');
    }

    public function resolver(): BelongsTo
    {
        return $this->belongsTo(User::class, 'resolved_by_user_id');
    }

    public function fieldTeam(): BelongsTo
    {
        return $this->belongsTo(FieldTeam::class);
    }

    public function servicePartner(): BelongsTo
    {
        return $this->belongsTo(ServicePartner::class);
    }
}

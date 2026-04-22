<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class MissionTaskSegment extends Model
{
    use HasFactory;

    protected $fillable = [
        'mission_batch_id',
        'mission_batch_day_id',
        'mission_id',
        'field_team_id',
        'service_partner_id',
        'assigned_user_id',
        'status',
        'segment_type',
        'title',
        'zone_label',
        'service_date',
        'planned_start_at',
        'planned_end_at',
        'estimated_minutes',
        'crew_size',
        'sequence',
        'metadata',
        'notes',
    ];

    protected $casts = [
        'service_date' => 'date',
        'planned_start_at' => 'datetime',
        'planned_end_at' => 'datetime',
        'estimated_minutes' => 'integer',
        'crew_size' => 'integer',
        'sequence' => 'integer',
        'metadata' => 'array',
    ];

    public function batch(): BelongsTo
    {
        return $this->belongsTo(MissionBatch::class, 'mission_batch_id');
    }

    public function day(): BelongsTo
    {
        return $this->belongsTo(MissionBatchDay::class, 'mission_batch_day_id');
    }

    public function mission(): BelongsTo
    {
        return $this->belongsTo(Mission::class);
    }

    public function fieldTeam(): BelongsTo
    {
        return $this->belongsTo(FieldTeam::class);
    }

    public function servicePartner(): BelongsTo
    {
        return $this->belongsTo(ServicePartner::class);
    }

    public function assignedUser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'assigned_user_id');
    }
}

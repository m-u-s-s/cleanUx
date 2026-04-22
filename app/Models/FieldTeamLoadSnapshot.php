<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class FieldTeamLoadSnapshot extends Model
{
    use HasFactory;

    protected $fillable = [
        'field_team_id',
        'snapshot_date',
        'active_missions_count',
        'planned_segments_count',
        'planned_minutes',
        'assigned_members_count',
        'capacity_minutes',
        'utilization_percent',
        'metadata',
    ];

    protected $casts = [
        'snapshot_date' => 'date',
        'active_missions_count' => 'integer',
        'planned_segments_count' => 'integer',
        'planned_minutes' => 'integer',
        'assigned_members_count' => 'integer',
        'capacity_minutes' => 'integer',
        'utilization_percent' => 'decimal:2',
        'metadata' => 'array',
    ];

    public function fieldTeam(): BelongsTo
    {
        return $this->belongsTo(FieldTeam::class);
    }
}

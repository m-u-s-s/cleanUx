<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class MissionBatchDay extends Model
{
    use HasFactory;

    protected $fillable = [
        'mission_batch_id',
        'field_team_id',
        'service_partner_id',
        'status',
        'service_date',
        'planned_start_at',
        'planned_end_at',
        'target_mission_count',
        'metadata',
        'notes',
    ];

    protected $casts = [
        'service_date' => 'date',
        'planned_start_at' => 'datetime',
        'planned_end_at' => 'datetime',
        'target_mission_count' => 'integer',
        'metadata' => 'array',
    ];

    public function batch(): BelongsTo
    {
        return $this->belongsTo(MissionBatch::class, 'mission_batch_id');
    }

    public function fieldTeam(): BelongsTo
    {
        return $this->belongsTo(FieldTeam::class);
    }

    public function servicePartner(): BelongsTo
    {
        return $this->belongsTo(ServicePartner::class);
    }

    public function segments(): HasMany
    {
        return $this->hasMany(MissionTaskSegment::class)->orderBy('sequence');
    }
}

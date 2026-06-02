<?php

namespace App\Models;

use Database\Factories\MissionTaskSegmentFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * @property ?Carbon $service_date
 * @property ?Carbon $planned_start_at
 * @property ?Carbon $planned_end_at
 * @property int $estimated_minutes
 * @property int $crew_size
 * @property int $sequence
 * @property array $metadata
 * @property ?int $field_team_id
 * @property ?int $assigned_user_id
 */
class MissionTaskSegment extends Model
{
    /** @use HasFactory<MissionTaskSegmentFactory> */
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

    /** @return BelongsTo<MissionBatch, $this> */
    public function batch(): BelongsTo
    {
        return $this->belongsTo(MissionBatch::class, 'mission_batch_id');
    }

    /** @return BelongsTo<MissionBatchDay, $this> */
    public function day(): BelongsTo
    {
        return $this->belongsTo(MissionBatchDay::class, 'mission_batch_day_id');
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

    /** @return BelongsTo<ServicePartner, $this> */
    public function servicePartner(): BelongsTo
    {
        return $this->belongsTo(ServicePartner::class);
    }

    /** @return BelongsTo<User, $this> */
    public function assignedUser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'assigned_user_id');
    }
}

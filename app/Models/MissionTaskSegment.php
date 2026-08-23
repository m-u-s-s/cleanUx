<?php

namespace App\Models;

use Database\Factories\MissionTaskSegmentFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;

/**
 * @property ?Carbon $service_date
 * @property ?Carbon $planned_start_at
 * @property ?Carbon $planned_end_at
 * @property ?int $estimated_minutes
 * @property ?int $crew_size
 * @property ?int $sequence
 * @property ?array $metadata
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

    /**
     * LES DEUX RELATIONS QUE LE PANNEAU CHEF D'ÉQUIPE RÉCLAMAIT SANS QU'ELLES EXISTENT
     * (ajoutées le 2026-08-06).
     *
     * `TeamLeadOperationsCenter` chargeait `with(['assignments.user', 'memberStatuses'])` et
     * `member-status-panel.blade.php` parcourt `$selectedSegment->assignments`. Ni l'une ni l'autre
     * n'était déclarée ici : l'eager-load levait une `RelationNotFoundException` et l'écran entier
     * tombait dès qu'un segment existait.
     *
     * TOUT LE RESTE ÉTAIT DÉJÀ LÀ, vérifié avant d'écrire : les tables
     * `mission_task_segment_assignments` et `mission_member_statuses`, leurs modèles avec `user()`,
     * `segment()` et `segmentAssignment()`, ainsi que l'action du composant et son service. Seul le
     * côté INVERSE manquait — `assignedUser` (au singulier, via `assigned_user_id`) désigne autre
     * chose : le responsable du segment, pas l'équipe affectée.
     *
     * @return HasMany<MissionTaskSegmentAssignment, $this>
     */
    public function assignments(): HasMany
    {
        return $this->hasMany(MissionTaskSegmentAssignment::class, 'mission_task_segment_id')
            ->orderBy('sequence_order');
    }

    /** @return HasMany<MissionMemberStatus, $this> */
    public function memberStatuses(): HasMany
    {
        return $this->hasMany(MissionMemberStatus::class, 'mission_task_segment_id');
    }
}

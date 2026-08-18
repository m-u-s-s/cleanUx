<?php

namespace App\Models;

use Database\Factories\MissionReinforcementRequestFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class MissionReinforcementRequest extends Model
{
    /** @use HasFactory<MissionReinforcementRequestFactory> */
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
        /*
         * TROIS COLONNES QUE LA TABLE PORTE ET QU'ELOQUENT ÉCARTAIT EN SILENCE.
         *
         * `required_people` est NOT NULL : une demande posée sans elle échouait au niveau SQL. Les
         * deux autres se perdaient sans un mot — l'équipe assignée et le moment du besoin, c'est-à-
         * dire précisément ce qui permet de trier les demandes.
         *
         * Elles n'avaient jamais servi parce que le seul écrivain était le centre du chef d'équipe,
         * qui les laissait vides. La demande depuis le terrain les remplit toutes les trois.
         */
        'provider_team_id',
        'required_people',
        'needed_at',
    ];

    protected $casts = [
        'required_people' => 'integer',
        'needed_at' => 'datetime',
        'requested_members' => 'integer',
        'requested_minutes' => 'integer',
        'resolved_at' => 'datetime',
    ];

    /** @return BelongsTo<Mission, $this> */
    public function mission(): BelongsTo
    {
        return $this->belongsTo(Mission::class);
    }

    /** @return BelongsTo<MissionBatch, $this> */
    public function batch(): BelongsTo
    {
        return $this->belongsTo(MissionBatch::class, 'mission_batch_id');
    }

    /** @return BelongsTo<MissionBatchDay, $this> */
    public function batchDay(): BelongsTo
    {
        return $this->belongsTo(MissionBatchDay::class, 'mission_batch_day_id');
    }

    /** @return BelongsTo<MissionTaskSegment, $this> */
    public function segment(): BelongsTo
    {
        return $this->belongsTo(MissionTaskSegment::class, 'mission_task_segment_id');
    }

    /** @return BelongsTo<User, $this> */
    public function requester(): BelongsTo
    {
        return $this->belongsTo(User::class, 'requested_by_user_id');
    }

    /** @return BelongsTo<User, $this> */
    public function resolver(): BelongsTo
    {
        return $this->belongsTo(User::class, 'resolved_by_user_id');
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
}

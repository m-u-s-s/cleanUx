<?php

namespace App\Models;

use Database\Factories\TimeEntryFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * CE QUI S'EST RÉELLEMENT PASSÉ (E20) — par opposition au shift, qui dit ce qui était prévu.
 *
 * Les confondre reviendrait à payer le prévu : cela arrange l'employeur les jours de retard et le
 * salarié les jours de dépassement, et fâche tout le monde le reste du temps.
 *
 * @property int $id
 * @property int $organization_account_id
 * @property int $user_id
 * @property int|null $mission_id
 * @property int|null $shift_id
 * @property \Illuminate\Support\Carbon $started_at
 * @property \Illuminate\Support\Carbon|null $ended_at
 * @property int $worked_minutes
 * @property int $paused_minutes
 * @property string $source
 * @property string $status
 */
class TimeEntry extends Model
{
    /** @use HasFactory<TimeEntryFactory> */
    use HasFactory;

    /** Relevée par la géo-barrière du suivi : elle fait foi sans autre formalité. */
    public const SOURCE_AUTO = 'auto';

    /** Saisie à la main : elle demande une approbation, sinon le pointage devient déclaratif. */
    public const SOURCE_MANUAL = 'manual';

    public const STATUS_RECORDED = 'recorded';

    public const STATUS_PENDING_APPROVAL = 'pending_approval';

    public const STATUS_APPROVED = 'approved';

    public const STATUS_REJECTED = 'rejected';

    protected $fillable = [
        'organization_account_id',
        'user_id',
        'mission_id',
        'shift_id',
        'started_at',
        'ended_at',
        'worked_minutes',
        'paused_minutes',
        'source',
        'status',
        'approved_by_user_id',
        'approved_at',
        'notes',
        'metadata',
    ];

    protected $casts = [
        'started_at' => 'datetime',
        'ended_at' => 'datetime',
        'approved_at' => 'datetime',
        'worked_minutes' => 'integer',
        'paused_minutes' => 'integer',
        'metadata' => 'array',
    ];

    /** @return BelongsTo<User, $this> */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /** @return BelongsTo<Mission, $this> */
    public function mission(): BelongsTo
    {
        return $this->belongsTo(Mission::class);
    }

    /** Cette ligne compte-t-elle dans les heures payables ? */
    public function estRetenue(): bool
    {
        return in_array($this->status, [self::STATUS_RECORDED, self::STATUS_APPROVED], true);
    }
}

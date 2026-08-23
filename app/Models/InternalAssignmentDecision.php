<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Pourquoi cette personne-là, et pas une autre.
 *
 * @property array<int, array<string, mixed>> $candidates
 */
class InternalAssignmentDecision extends Model
{
    /** Un humain a appuyé sur « assigner » pour une mission précise. */
    public const MODE_MANUAL = 'manual';

    /** Un humain a appuyé sur « tout assigner ». */
    public const MODE_AUTO_BUTTON = 'auto_button';

    /** Personne n'a appuyé : la mission vient de naître et la société est en mode continu. */
    public const MODE_AUTO_MODE = 'auto_mode';

    public const STATUS_ASSIGNED = 'assigned';

    /** Aucun candidat libre — l'owner est alerté IMMÉDIATEMENT, pas en fin de traitement. */
    public const STATUS_NO_CANDIDATE = 'no_candidate';

    /** La mission a été prise entre-temps par quelqu'un d'autre : on ne la touche pas. */
    public const STATUS_SKIPPED_LOCKED = 'skipped_locked';

    protected $fillable = [
        'mission_id',
        'provider_organization_id',
        'triggered_by',
        'mode',
        'status',
        'chosen_user_id',
        'chosen_score',
        'candidates',
    ];

    protected $casts = [
        'candidates' => 'array',
        'chosen_score' => 'integer',
    ];

    /** @return BelongsTo<Mission, $this> */
    public function mission(): BelongsTo
    {
        return $this->belongsTo(Mission::class);
    }

    /** @return BelongsTo<User, $this> */
    public function chosenUser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'chosen_user_id');
    }
}

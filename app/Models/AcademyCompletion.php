<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * QUELQU'UN A TERMINÉ UNE FORMATION (E16).
 *
 * `badge_granted_at` EST SÉPARÉ DE `completed_at` : terminer et voir son badge apparaître sont deux
 * événements, et l'attribution passe par le module Badges — qui peut échouer sans que la complétion
 * doive être perdue.
 *
 * @property int $id
 * @property int $academy_course_id
 * @property int $user_id
 * @property \Illuminate\Support\Carbon $completed_at
 * @property \Illuminate\Support\Carbon|null $badge_granted_at
 */
class AcademyCompletion extends Model
{
    protected $fillable = [
        'academy_course_id', 'user_id', 'completed_at', 'score_percent', 'badge_granted_at',
    ];

    protected $casts = [
        'completed_at' => 'datetime',
        'badge_granted_at' => 'datetime',
        'score_percent' => 'integer',
    ];

    /** @return BelongsTo<AcademyCourse, $this> */
    public function course(): BelongsTo
    {
        return $this->belongsTo(AcademyCourse::class, 'academy_course_id');
    }

    /** @return BelongsTo<User, $this> */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}

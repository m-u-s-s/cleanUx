<?php

namespace App\Models;

use Database\Factories\LeaveRequestFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * UNE DEMANDE D'ABSENCE (E21).
 *
 * Ce qui compte n'est pas le tableau des congés : c'est qu'une demande APPROUVÉE empêche
 * l'assignation. Sans ce lien, le prestataire reçoit sa course le premier jour de ses vacances,
 * refuse, et le moteur cherche quelqu'un d'autre — après avoir perdu vingt secondes et une occasion.
 *
 * @property int $id
 * @property int $organization_account_id
 * @property int $user_id
 * @property string $type
 * @property Carbon $starts_on
 * @property Carbon $ends_on
 * @property string $status
 */
class LeaveRequest extends Model
{
    /** @use HasFactory<LeaveRequestFactory> */
    use HasFactory;

    public const STATUS_PENDING = 'pending';

    /** Le seul état qui bloque le planning. */
    public const STATUS_APPROVED = 'approved';

    /** Conservé : un refus qu'on efface, c'est une conversation qui recommence deux mois plus tard. */
    public const STATUS_REJECTED = 'rejected';

    public const STATUS_CANCELLED = 'cancelled';

    protected $fillable = [
        'organization_account_id',
        'user_id',
        'type',
        'starts_on',
        'ends_on',
        'status',
        'reason',
        'decided_by_user_id',
        'decided_at',
        'decision_note',
        'metadata',
    ];

    protected $casts = [
        'starts_on' => 'date',
        'ends_on' => 'date',
        'decided_at' => 'datetime',
        'metadata' => 'array',
    ];

    /** @return BelongsTo<User, $this> */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * Cette absence couvre-t-elle ce jour ?
     *
     * Bornes INCLUSIVES des deux côtés : un congé du 3 au 7 couvre le 7. L'exclure ferait travailler
     * quelqu'un le dernier jour de ses vacances, ce qu'aucun formulaire ne laisse supposer.
     */
    public function couvre(Carbon $jour): bool
    {
        return $this->status === self::STATUS_APPROVED
            && $jour->startOfDay()->betweenIncluded($this->starts_on, $this->ends_on);
    }
}

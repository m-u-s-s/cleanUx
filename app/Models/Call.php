<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * Un appel passé depuis un canal d'équipe.
 *
 * @property ?Carbon $started_at
 * @property ?Carbon $answered_at
 * @property ?Carbon $ended_at
 */
class Call extends Model
{
    /** Ça sonne — personne n'a encore décroché. */
    public const STATUS_RINGING = 'ringing';

    public const STATUS_ACTIVE = 'active';

    public const STATUS_ENDED = 'ended';

    /**
     * Personne n'a décroché.
     *
     * L'ÉTAT LE PLUS UTILE DES QUATRE, et le seul que le serveur de médias ne connaît pas : LiveKit
     * sait qui est dans une salle à l'instant T, pas qu'un appel a sonné dans le vide à 7 h.
     */
    public const STATUS_MISSED = 'missed';

    protected $fillable = [
        'channel_id',
        'initiator_user_id',
        'type',
        'status',
        'room_name',
        'started_at',
        'answered_at',
        'ended_at',
    ];

    protected $casts = [
        'started_at' => 'datetime',
        'answered_at' => 'datetime',
        'ended_at' => 'datetime',
    ];

    /** @return BelongsTo<Channel, $this> */
    public function channel(): BelongsTo
    {
        return $this->belongsTo(Channel::class);
    }

    /** @return BelongsTo<User, $this> */
    public function initiator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'initiator_user_id');
    }
}

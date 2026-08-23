<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * LE RÈGLEMENT DU TEMPS SUPPLÉMENTAIRE D'UNE MISSION — l'attestation, puis l'encaissement.
 *
 * @property int $id
 * @property int $mission_id
 * @property int $booking_id
 * @property int $authorized_minutes
 * @property int $purchased_minutes
 * @property int $elapsed_minutes
 * @property int $extension_minutes
 * @property int $overtime_minutes
 * @property int $grace_minutes
 * @property bool $capped
 * @property int|null $effective_hourly_rate_cents
 * @property float $overtime_multiplier
 * @property int $authorized_amount_cents
 * @property int $extension_amount_cents
 * @property int $overtime_amount_cents
 * @property int $amount_due_cents
 * @property string $currency
 * @property string $status
 * @property string|null $stripe_payment_intent_id
 * @property Carbon|null $charged_at
 * @property int $attempts
 * @property Carbon|null $last_attempt_at
 * @property string|null $last_error
 */
class MissionTimeSettlement extends Model
{
    /** Rien à réclamer : la mission a tenu dans son temps. Un état, pas une absence. */
    public const STATUT_SANS_OBJET = 'not_required';

    /** Il y a une créance, elle n'est pas encore encaissée. */
    public const STATUT_EN_ATTENTE = 'pending';

    /** Stripe l'a confirmé. C'est le SEUL chemin vers cette valeur. */
    public const STATUT_ENCAISSE = 'charged';

    /** La tentative a échoué, avec son motif. La reprise planifiée s'en occupe. */
    public const STATUT_ECHOUE = 'failed';

    protected $table = 'mission_time_settlements';

    /** LES COLONNES D'ARGENT ET DE STATUT SONT HORS `$fillable`, délibérément. */
    protected $fillable = [
        'mission_id',
        'booking_id',
        'authorized_minutes',
        'purchased_minutes',
        'elapsed_minutes',
        'extension_minutes',
        'overtime_minutes',
        'grace_minutes',
        'capped',
        'effective_hourly_rate_cents',
        'overtime_multiplier',
        'currency',
    ];

    protected $casts = [
        'authorized_minutes' => 'integer',
        'purchased_minutes' => 'integer',
        'elapsed_minutes' => 'integer',
        'extension_minutes' => 'integer',
        'overtime_minutes' => 'integer',
        'grace_minutes' => 'integer',
        'capped' => 'boolean',
        'effective_hourly_rate_cents' => 'integer',
        'overtime_multiplier' => 'float',
        'authorized_amount_cents' => 'integer',
        'extension_amount_cents' => 'integer',
        'overtime_amount_cents' => 'integer',
        'amount_due_cents' => 'integer',
        'attempts' => 'integer',
        'charged_at' => 'datetime',
        'last_attempt_at' => 'datetime',
    ];

    /** LES DÉFAUTS SQL NE PEUPLENT PAS L'OBJET EN MÉMOIRE. */
    protected static function booted(): void
    {
        static::creating(function (self $reglement) {
            $reglement->status ??= self::STATUT_EN_ATTENTE;
            $reglement->authorized_amount_cents ??= 0;
            $reglement->extension_amount_cents ??= 0;
            $reglement->overtime_amount_cents ??= 0;
            $reglement->amount_due_cents ??= 0;
            $reglement->attempts ??= 0;
        });
    }

    /** @return BelongsTo<Mission, $this> */
    public function mission(): BelongsTo
    {
        return $this->belongsTo(Mission::class);
    }

    /** @return BelongsTo<Booking, $this> */
    public function booking(): BelongsTo
    {
        return $this->belongsTo(Booking::class);
    }

    public function estUneCreance(): bool
    {
        return in_array($this->status, [self::STATUT_EN_ATTENTE, self::STATUT_ECHOUE], true)
            && $this->amount_due_cents > 0;
    }
}

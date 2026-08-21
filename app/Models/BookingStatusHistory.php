<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * L'HISTORIQUE DES STATUTS D'UNE RÉSERVATION.
 *
 * La table existait depuis l'origine, complète et bien dessinée — `from_status`, `to_status`,
 * `changed_by`, `note`, `metadata` — et AUCUN modèle ne la portait, aucun code ne l'écrivait.
 * C'est la famille dominante de ce dépôt : un module entier, prêt, injoignable.
 *
 * Ce qu'on a vérifié avant de la brancher plutôt que de la supprimer :
 *
 *   - `Booking` ne porte pas le trait `AuditsEloquentEvents`, qui équipe pourtant sept autres
 *     modèles. Les changements de statut d'une réservation n'étaient donc pas audités.
 *   - `BookingObserver` n'en gardait aucune trace.
 *   - `mission_events` tient une chronologie de TERRAIN (`event_type`, `happened_at`), écrite par
 *     MissionFieldActionController et MissionTimelineService. C'est une autre notion : ce qui s'est
 *     passé sur place, pas « ce statut est passé de X à Y, par qui ».
 *   - `booking_reschedule_history` ne couvre que les reprogrammations.
 *
 * Autrement dit : personne ne pouvait dire qui avait annulé une réservation, ni quand, ni depuis
 * quel état. Or les frais d'annulation se calculent sur ce chemin, et un litige se tranche dessus.
 */
class BookingStatusHistory extends Model
{
    protected $table = 'booking_status_histories';

    protected $fillable = [
        'booking_id',
        'changed_by',
        'from_status',
        'to_status',
        'note',
        'metadata',
    ];

    protected $casts = [
        'metadata' => 'array',
    ];

    /** @return BelongsTo<Booking, $this> */
    public function booking(): BelongsTo
    {
        return $this->belongsTo(Booking::class, 'booking_id');
    }

    /** L'auteur du changement — nul quand il vient d'une commande ou d'une file. */
    /** @return BelongsTo<User, $this> */
    public function auteur(): BelongsTo
    {
        return $this->belongsTo(User::class, 'changed_by');
    }
}

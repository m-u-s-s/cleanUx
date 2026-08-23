<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** L'HISTORIQUE DES STATUTS D'UNE RÉSERVATION. */
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

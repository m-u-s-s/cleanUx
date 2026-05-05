<?php

namespace App\Models;

/**
 * Transitional alias.
 * V2 table = bookings. Old code can still import RendezVous.
 */
class RendezVous extends Booking
{
    protected $table = 'bookings';
    protected $fillable = [
        'client_id',
        'employe_id',
        'date',
        'heure',
        'adresse',
        'ville',
        'code_postal',
        'telephone_client',
        'commentaire_client',
        'devis_estime',
        'duree_estimee',

    ];
}

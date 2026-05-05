<?php

namespace App\Models;

/**
 * Transitional alias.
 * V2 table = bookings. Old code can still import RendezVous.
 */
class RendezVous extends Booking
{
    protected $table = 'bookings';
}

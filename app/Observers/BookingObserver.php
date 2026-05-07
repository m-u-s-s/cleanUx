<?php

namespace App\Observers;

use App\Models\Booking;
use App\Services\Missions\MissionLifecycleService;

class BookingObserver
{
    public function saved(Booking $booking): void
{
    if ($booking->customer_organization_id) {
        // Invalider toutes les clés analytics:* pour cette org
        // (avec Redis : SCAN + DEL ; avec file/db : laisser expirer naturellement)
    }
}
}
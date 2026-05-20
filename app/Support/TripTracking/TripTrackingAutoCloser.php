<?php

namespace App\Support\TripTracking;

use App\Models\Booking;
use App\Models\TripTrackingSession;
use App\Services\TripTracking\TripTrackingService;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;

/**
 * Helper soft-fail pour auto-terminer les sessions Trip Tracking
 * quand un booking passe à completed/cancelled.
 *
 * Appelé depuis BookingObserver::saved. Skip silencieusement si :
 *   - module Trip Tracking pas installé (table absente)
 *   - feature désactivée (config)
 *   - pas de session active sur ce booking
 */
class TripTrackingAutoCloser
{
    public static function endSessionForBooking(Booking $booking, string $reason): void
    {
        try {
            if (! Schema::hasTable('trip_tracking_sessions')) {
                return;
            }
            if (! (bool) config('trip_tracking.enabled', true)) {
                return;
            }

            $sessions = TripTrackingSession::query()
                ->where('booking_id', $booking->id)
                ->active()
                ->get();

            if ($sessions->isEmpty()) {
                return;
            }

            $service = app(TripTrackingService::class);
            foreach ($sessions as $session) {
                $service->endSession($session, $reason);
            }
        } catch (\Throwable $e) {
            Log::warning('[trip_tracking_auto] endSession failed', [
                'booking_id' => $booking->id,
                'reason' => $reason,
                'error' => $e->getMessage(),
            ]);
        }
    }
}

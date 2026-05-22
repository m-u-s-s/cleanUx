<?php

namespace App\Services\Theme;

use App\Models\Booking;
use App\Models\Mission;
use App\Models\User;
use Carbon\Carbon;

class AdaptiveThemeResolver
{
    /**
     * Mission statuses that indicate the user (as client or provider) is actively
     * engaged in a field operation.
     *
     * Note: the missions table does not have a direct client_id column.
     * Client-side missions are resolved via bookings.customer_user_id.
     * Provider-side missions are resolved via missions.lead_provider_user_id.
     *
     * Valid mission statuses in this codebase:
     * planned, assigned, en_route, arrived, started, paused, completed, cancelled.
     */
    private const ACTIVE_MISSION_STATUSES = ['en_route', 'arrived', 'started'];

    private const URGENT_NIGHT_HOUR_START = 21;
    private const URGENT_NIGHT_HOUR_END   = 6;

    public function resolveForUser(User $user): string
    {
        $preference = data_get($user->settings, 'theme_preference', 'auto');

        if ($preference === 'light' || $preference === 'dark') {
            return $preference;
        }

        if ($this->hasActiveMission($user)) {
            return 'dark';
        }

        if ($this->hasUrgentPendingDuringNight($user)) {
            return 'dark';
        }

        return 'light';
    }

    private function hasActiveMission(User $user): bool
    {
        // Provider-side: the user leads the mission directly
        $asProvider = Mission::query()
            ->where('lead_provider_user_id', $user->id)
            ->whereIn('status', self::ACTIVE_MISSION_STATUSES)
            ->exists();

        if ($asProvider) {
            return true;
        }

        // Client-side: the user owns a booking that has an active mission linked to it
        $clientBookingIds = Booking::query()
            ->where('customer_user_id', $user->id)
            ->pluck('id');

        if ($clientBookingIds->isEmpty()) {
            return false;
        }

        return Mission::query()
            ->where(function ($q) use ($clientBookingIds) {
                $q->whereIn('booking_id', $clientBookingIds)
                  ->orWhereIn('rendez_vous_id', $clientBookingIds);
            })
            ->whereIn('status', self::ACTIVE_MISSION_STATUSES)
            ->exists();
    }

    private function hasUrgentPendingDuringNight(User $user): bool
    {
        $hour   = Carbon::now()->hour;
        $isNight = $hour >= self::URGENT_NIGHT_HOUR_START || $hour < self::URGENT_NIGHT_HOUR_END;

        if (! $isNight) {
            return false;
        }

        return Booking::query()
            ->where('customer_user_id', $user->id)
            ->where('priority', 'urgent')
            ->whereNotIn('status', ['completed', 'cancelled', 'refunded', 'termine', 'annule'])
            ->exists();
    }
}

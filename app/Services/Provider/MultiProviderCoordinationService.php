<?php

namespace App\Services\Provider;

use App\Models\Booking;
use App\Models\Mission;
use App\Models\MissionAssignment;

/**
 * 7.9 — Multi-provider coordination for large or complex bookings.
 *
 * Interface defined; orchestration logic deferred.
 *
 * Use-case:
 *   A large office cleaning (2 000 m²), a multi-room renovation, or a
 *   full-building painting job requires 2–8 simultaneous providers.
 *   The current dispatch model sends one offer at a time.
 *
 * Architecture plan:
 *   - Booking carries a `provider_count_required` field (default 1).
 *   - MultiProviderCoordinationService creates N parallel MissionAssignment
 *     slots, runs dispatch for each simultaneously.
 *   - Mission status = 'partially_staffed' until all N slots filled.
 *   - Mission = 'fully_staffed' when all accepted.
 *   - Team lead is the first provider to accept.
 *   - If after X minutes not fully staffed, auto-fill from available pool
 *     or alert ops.
 *   - Payout splits: equal or role-weighted (lead_provider gets +15%).
 *
 * TODO: add provider_count_required to bookings table (migration)
 * TODO: implement parallel dispatch (N concurrent offers)
 * TODO: add 'partially_staffed' / 'fully_staffed' to MissionStatus enum
 * TODO: implement team payout split calculator
 * TODO: add provider_team_id to missions for grouping
 */
class MultiProviderCoordinationService
{
    /**
     * Dispatch a booking to multiple providers simultaneously.
     *
     * @param  Mission  $mission  The mission needing multiple providers
     * @param  int  $providersRequired  Number of providers needed
     * @return array<int, MissionAssignment> Created assignments
     *
     * TODO: implement
     */
    public function dispatchMultiple(Mission $mission, int $providersRequired): array
    {
        throw new \RuntimeException('[MultiProviderCoordinationService] Not implemented — TODO.');
    }

    /**
     * Check if a mission has all required providers confirmed.
     *
     * TODO: implement
     */
    public function isFullyStaffed(Mission $mission): bool
    {
        return false; // safe default until implemented
    }
}

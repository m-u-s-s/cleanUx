<?php

namespace App\Support\Quality;

use App\Models\Booking;
use App\Models\Mission;
use App\Models\MissionQualityInspection;
use App\Models\User;

/**
 * Ownership resolution for Quality inspections (C1).
 *
 * A Quality inspection belongs to a mission, which is tied to a booking. Only the booking's
 * client (the party who can validate/dispute) and the mission's assigned provider(s) (the
 * party who can fill it in) may read or mutate an inspection. These helpers are the single
 * source of truth, reused by the policy (controller layer) and by the service guards
 * (defense-in-depth), so an inspection id cannot be enumerated to read or alter another
 * user's quality records.
 */
class QualityInspectionAccess
{
    /**
     * Resolve the client + provider user ids tied to a mission (and an optional booking id
     * carried directly on the inspection).
     *
     * @return array{clients: int[], providers: int[]}
     */
    public static function partiesForMission(int $missionId, ?int $bookingId = null): array
    {
        $clients = [];
        $providers = [];

        $mission = Mission::query()->with('assignments')->find($missionId);

        $bookingIds = [];
        if ($mission) {
            if ($mission->lead_provider_user_id) {
                $providers[] = (int) $mission->lead_provider_user_id;
            }
            foreach ($mission->assignments as $assignment) {
                $status = $assignment->assignment_status ?? $assignment->status ?? null;
                if (in_array($status, ['accepted', 'arrived', 'completed', 'in_progress', 'started'], true)
                    && $assignment->user_id) {
                    $providers[] = (int) $assignment->user_id;
                }
            }
            $bookingIds[] = $mission->booking_id;
        }
        $bookingIds[] = $bookingId;

        $bookingIds = array_values(array_unique(array_filter(array_map('intval', $bookingIds))));
        if ($bookingIds) {
            foreach (Booking::query()->whereIn('id', $bookingIds)->get() as $booking) {
                foreach (['client_id', 'customer_user_id'] as $col) {
                    if (! empty($booking->{$col})) {
                        $clients[] = (int) $booking->{$col};
                    }
                }
                foreach (['employe_id', 'assigned_provider_user_id'] as $col) {
                    if (! empty($booking->{$col})) {
                        $providers[] = (int) $booking->{$col};
                    }
                }
            }
        }

        return [
            'clients' => array_values(array_unique($clients)),
            'providers' => array_values(array_unique($providers)),
        ];
    }

    public static function clientCanAccess(User $user, MissionQualityInspection $inspection): bool
    {
        $parties = self::partiesForMission((int) $inspection->mission_id, $inspection->booking_id ? (int) $inspection->booking_id : null);

        return in_array((int) $user->id, $parties['clients'], true);
    }

    public static function providerCanAccess(User $user, MissionQualityInspection $inspection): bool
    {
        $parties = self::partiesForMission((int) $inspection->mission_id, $inspection->booking_id ? (int) $inspection->booking_id : null);

        return in_array((int) $user->id, $parties['providers'], true);
    }

    public static function providerCanAccessMission(User $user, int $missionId, ?int $bookingId = null): bool
    {
        $parties = self::partiesForMission($missionId, $bookingId);

        return in_array((int) $user->id, $parties['providers'], true);
    }
}

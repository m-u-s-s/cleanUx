<?php

namespace App\Services\Missions;

use App\Events\MissionPositionUpdated;
use App\Models\Mission;
use App\Models\MissionAssignment;
use App\Models\MissionTrackingPoint;
use App\Models\MissionTrackingSession;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use RuntimeException;

class MissionTrackingService
{
    public function startToClientTracking(Mission $mission, User $employee, float $lat, float $lng): MissionTrackingSession
    {
        if (! in_array($mission->status, ['assigned', 'en_route'])) {
            throw new RuntimeException('La mission ne peut pas démarrer le tracking trajet.');
        }

        if (! $employee->isEmploye()) {
            throw new RuntimeException('Seul un employé peut démarrer le tracking.');
        }

        $isAssigned = $mission->lead_employee_id === $employee->id
            || MissionAssignment::query()
            ->where('mission_id', $mission->id)
            ->where('user_id', $employee->id)
            ->exists();

        if (! $isAssigned) {
            throw new RuntimeException('Cet employé n’est pas assigné à cette mission.');
        }

        return DB::transaction(function () use ($mission, $employee, $lat, $lng) {
            MissionTrackingSession::query()
                ->where('mission_id', $mission->id)
                ->where('is_active', true)
                ->update([
                    'is_active' => false,
                    'ended_at' => now(),
                ]);

            $assignment = MissionAssignment::query()
                ->where('mission_id', $mission->id)
                ->where('user_id', $employee->id)
                ->first();

            if (! $assignment && $mission->lead_employee_id !== $employee->id) {
                throw new RuntimeException('Aucune affectation mission trouvée pour cet employé.');
            }

            $session = MissionTrackingSession::query()->create([
                'mission_id' => $mission->id,
                'assignment_id' => $assignment?->id,
                'employee_user_id' => $employee->id,
                'tracking_mode' => 'to_client',
                'is_client_visible' => true,
                'is_active' => true,
                'started_at' => now(),
                'start_lat' => $lat,
                'start_lng' => $lng,
                'last_lat' => $lat,
                'last_lng' => $lng,
                'point_count' => 0,
                'distance_meters' => 0,
            ]);

            $this->storePoint($session, [
                'lat' => $lat,
                'lng' => $lng,
                'accuracy_meters' => null,
                'speed_kmh' => null,
                'heading' => null,
                'battery_level' => null,
                'source' => 'browser',
                'app_state' => 'foreground',
                'meta' => null,
            ]);

            $mission->update([
                'status' => 'en_route',
            ]);

            return $session->fresh(['points']);
        });
    }

    public function pushPoint(MissionTrackingSession $session, array $payload): MissionTrackingSession
    {
        if (! $session->is_active) {
            throw new RuntimeException('La session de tracking est inactive.');
        }

        return DB::transaction(function () use ($session, $payload) {
            $lastLat = (float) ($session->last_lat ?? $payload['lat']);
            $lastLng = (float) ($session->last_lng ?? $payload['lng']);

            $distance = $this->distanceMeters(
                $lastLat,
                $lastLng,
                (float) $payload['lat'],
                (float) $payload['lng']
            );

            $this->storePoint($session, $payload);

            $session->update([
                'last_lat' => $payload['lat'],
                'last_lng' => $payload['lng'],
                'point_count' => $session->point_count + 1,
                'distance_meters' => $session->distance_meters + max(0, (int) round($distance)),
            ]);

            $freshSession = $session->fresh(['points', 'mission']);

            event(new MissionPositionUpdated(
                (int) $freshSession->mission_id,
                $this->livePayload($freshSession->mission)
            ));

            return $freshSession;
        });
    }

    public function stopTracking(MissionTrackingSession $session, ?float $lat = null, ?float $lng = null): MissionTrackingSession
    {
        $session->update([
            'is_active' => false,
            'ended_at' => now(),
            'last_lat' => $lat ?? $session->last_lat,
            'last_lng' => $lng ?? $session->last_lng,
        ]);

        return $session->fresh();
    }

    protected function storePoint(MissionTrackingSession $session, array $payload): MissionTrackingPoint
    {
        return MissionTrackingPoint::query()->create([
            'tracking_session_id' => $session->id,
            'recorded_at' => now(),
            'lat' => $payload['lat'],
            'lng' => $payload['lng'],
            'accuracy_meters' => $payload['accuracy_meters'] ?? null,
            'speed_kmh' => $payload['speed_kmh'] ?? null,
            'heading' => $payload['heading'] ?? null,
            'battery_level' => $payload['battery_level'] ?? null,
            'source' => $payload['source'] ?? 'browser',
            'app_state' => $payload['app_state'] ?? null,
            'meta' => $payload['meta'] ?? null,
        ]);
    }

    protected function distanceMeters(float $lat1, float $lng1, float $lat2, float $lng2): float
    {
        $earthRadius = 6371000;

        $dLat = deg2rad($lat2 - $lat1);
        $dLng = deg2rad($lng2 - $lng1);

        $a = sin($dLat / 2) ** 2
            + cos(deg2rad($lat1)) * cos(deg2rad($lat2))
            * sin($dLng / 2) ** 2;

        $c = 2 * atan2(sqrt($a), sqrt(1 - $a));

        return $earthRadius * $c;
    }


    public function stopActiveForMission(Mission $mission, ?float $lat = null, ?float $lng = null): ?MissionTrackingSession
    {
        $session = MissionTrackingSession::query()
            ->where('mission_id', $mission->id)
            ->where('is_active', true)
            ->latest('id')
            ->first();

        if (! $session) {
            return null;
        }

        return $this->stopTracking($session, $lat, $lng);
    }

    public function livePayload(Mission $mission): array
    {
        $mission->loadMissing(['rendezVous', 'leadEmployee', 'activeTrackingSession']);

        $session = $mission->activeTrackingSession;

        $latestPoint = $session
            ? MissionTrackingPoint::query()
            ->where('tracking_session_id', $session->id)
            ->latest('recorded_at')
            ->first()
            : null;

        $employeeLat = $latestPoint?->lat ? (float) $latestPoint->lat : ($session?->last_lat !== null ? (float) $session->last_lat : null);
        $employeeLng = $latestPoint?->lng ? (float) $latestPoint->lng : ($session?->last_lng !== null ? (float) $session->last_lng : null);

        $destinationLat = $mission->destination_lat !== null ? (float) $mission->destination_lat : null;
        $destinationLng = $mission->destination_lng !== null ? (float) $mission->destination_lng : null;

        $distanceMeters = null;
        $etaMinutes = null;

        if ($employeeLat !== null && $employeeLng !== null && $destinationLat !== null && $destinationLng !== null) {
            $distanceMeters = (int) round($this->distanceMeters(
                $employeeLat,
                $employeeLng,
                $destinationLat,
                $destinationLng
            ));

            $speed = (float) ($latestPoint?->speed_kmh ?? 30);
            $speed = max($speed, 12);

            $etaMinutes = (int) ceil((($distanceMeters / 1000) / $speed) * 60);
        }

        return [
            'mission_id' => $mission->id,
            'status' => $mission->status,
            'employee' => [
                'id' => $mission->leadEmployee?->id,
                'name' => $mission->leadEmployee?->name,
            ],
            'session' => [
                'id' => $session?->id,
                'is_active' => (bool) ($session?->is_active),
                'tracking_mode' => $session?->tracking_mode,
                'started_at' => optional($session?->started_at)?->toISOString(),
            ],
            'employee_position' => [
                'lat' => $employeeLat,
                'lng' => $employeeLng,
                'recorded_at' => optional($latestPoint?->recorded_at)?->toISOString(),
                'accuracy_meters' => $latestPoint?->accuracy_meters !== null ? (float) $latestPoint->accuracy_meters : null,
                'speed_kmh' => $latestPoint?->speed_kmh !== null ? (float) $latestPoint->speed_kmh : null,
            ],
            'destination' => [
                'lat' => $destinationLat,
                'lng' => $destinationLng,
            ],
            'distance_meters' => $distanceMeters,
            'eta_minutes' => $etaMinutes,
        ];
    }
}

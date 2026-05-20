<?php

namespace App\Services\TripTracking;

use App\Events\Realtime\MissionLiveEta;
use App\Events\Realtime\MissionLivePosition;
use App\Models\Booking;
use App\Models\TripTrackingPoint;
use App\Models\TripTrackingSession;
use App\Models\User;
use App\Services\GeolocationV2\DistanceCalculator;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\ValidationException;

/**
 * TripTrackingService — tracking GPS provider→client en mission active.
 *
 * Workflow :
 *  1. startSession : crée session enroute, snapshot destination depuis booking
 *  2. recordPing : ajoute un point GPS, calcule distance/ETA, broadcast realtime
 *  3. Auto-transition vers arrived si geofence atteinte
 *  4. markInMission : provider démarre la mission (transition manuelle)
 *  5. endSession : terminé manuel ou auto-fin si booking devient completed
 *
 * Sécurité : ownership check via authorizeProvider (caller responsibility).
 */
class TripTrackingService
{
    public function __construct(
        protected DistanceCalculator $distance,
    ) {
    }

    /**
     * Démarre une session tracking pour un booking.
     * Idempotent : retourne la session active existante si présente.
     */
    public function startSession(
        User $provider,
        Booking $booking,
        ?float $startLat = null,
        ?float $startLng = null,
    ): TripTrackingSession {
        // Idempotency : pas plus d'une session active par (provider, booking)
        $existing = TripTrackingSession::query()
            ->where('booking_id', $booking->id)
            ->where('provider_user_id', $provider->id)
            ->active()
            ->first();
        if ($existing) {
            return $existing;
        }

        // Snapshot destination depuis booking
        [$destLat, $destLng] = $this->resolveBookingDestination($booking);
        $radiusM = (int) Config::get('trip_tracking.geofence_radius_m', 150);

        return TripTrackingSession::query()->create([
            'code' => TripTrackingSession::generateCode(),
            'booking_id' => $booking->id,
            'provider_user_id' => $provider->id,
            'status' => TripTrackingSession::STATUS_ENROUTE,
            'destination_lat' => $destLat,
            'destination_lng' => $destLng,
            'geofence_radius_m' => $radiusM,
            'start_lat' => $startLat,
            'start_lng' => $startLng,
            'started_at' => now(),
        ]);
    }

    /**
     * Ajoute un point GPS à la session active.
     * Calcule distance cumulative + distance-to-destination + ETA.
     * Auto-transition vers arrived si geofence atteinte.
     * Broadcast realtime via [[realtime-module]].
     */
    public function recordPing(
        TripTrackingSession $session,
        float $lat,
        float $lng,
        ?float $accuracyM = null,
        ?float $speedMps = null,
        ?float $headingDeg = null,
        ?string $clientSequence = null,
    ): TripTrackingPoint {
        if (! $session->isActive()) {
            throw ValidationException::withMessages([
                'session' => ['Session non active.'],
            ]);
        }

        // Dedup par client_sequence si fourni
        if ($clientSequence) {
            $existing = TripTrackingPoint::query()
                ->where('session_id', $session->id)
                ->where('client_sequence', $clientSequence)
                ->first();
            if ($existing) {
                return $existing;
            }
        }

        return DB::transaction(function () use ($session, $lat, $lng, $accuracyM, $speedMps, $headingDeg, $clientSequence) {
            // Distance cumulative depuis dernier point
            $cumulative = (int) $session->total_distance_m;
            if ($session->last_lat !== null && $session->last_lng !== null) {
                $deltaM = (int) round($this->distance->distanceMeters(
                    (float) $session->last_lat,
                    (float) $session->last_lng,
                    $lat,
                    $lng,
                ));
                $cumulative += $deltaM;
            }

            // Distance-to-destination
            $distToDest = null;
            $etaSeconds = null;
            if ($session->destination_lat !== null && $session->destination_lng !== null) {
                $distToDest = (int) round($this->distance->distanceMeters(
                    $lat,
                    $lng,
                    (float) $session->destination_lat,
                    (float) $session->destination_lng,
                ));
                // ETA estimé : speed actuel sinon avg (40km/h = 11.11 mps urbain par défaut)
                $effectiveSpeed = ($speedMps && $speedMps > 1.0) ? $speedMps : 11.11;
                $etaSeconds = (int) round($distToDest / $effectiveSpeed);
            }

            $point = TripTrackingPoint::query()->create([
                'session_id' => $session->id,
                'lat' => $lat,
                'lng' => $lng,
                'accuracy_m' => $accuracyM,
                'speed_mps' => $speedMps,
                'heading_deg' => $headingDeg,
                'cumulative_distance_m' => $cumulative,
                'distance_to_dest_m' => $distToDest,
                'eta_seconds' => $etaSeconds,
                'client_sequence' => $clientSequence,
                'recorded_at' => now(),
                'created_at' => now(),
            ]);

            // Update session aggregates
            $session->update([
                'points_count' => (int) $session->points_count + 1,
                'total_distance_m' => $cumulative,
                'current_eta_seconds' => $etaSeconds,
                'last_lat' => $lat,
                'last_lng' => $lng,
                'last_speed_mps' => $speedMps,
                'last_ping_at' => now(),
            ]);

            // Auto-transition vers arrived si dans geofence
            if (
                $session->status === TripTrackingSession::STATUS_ENROUTE
                && $distToDest !== null
                && $distToDest <= (int) $session->geofence_radius_m
            ) {
                $session->update([
                    'status' => TripTrackingSession::STATUS_ARRIVED,
                    'arrived_at' => now(),
                    'current_eta_seconds' => 0,
                ]);
            }

            // Broadcast realtime (soft-fail si module absent)
            $this->broadcastPing($session->fresh(), $point);

            return $point;
        });
    }

    /**
     * Provider démarre la mission (après être arrivé).
     */
    public function markInMission(TripTrackingSession $session): TripTrackingSession
    {
        if ($session->status === TripTrackingSession::STATUS_IN_MISSION) {
            return $session;
        }
        if (! in_array($session->status, [TripTrackingSession::STATUS_ENROUTE, TripTrackingSession::STATUS_ARRIVED], true)) {
            throw ValidationException::withMessages([
                'status' => ['Transition impossible vers in_mission.'],
            ]);
        }
        $session->update([
            'status' => TripTrackingSession::STATUS_IN_MISSION,
            'in_mission_at' => now(),
        ]);
        return $session->fresh();
    }

    /**
     * Termine la session (manuelle ou auto via booking completion observer).
     */
    public function endSession(TripTrackingSession $session, ?string $reason = null): TripTrackingSession
    {
        if (in_array($session->status, [TripTrackingSession::STATUS_ENDED, TripTrackingSession::STATUS_CANCELLED], true)) {
            return $session;
        }
        $meta = $session->metadata ?? [];
        if ($reason) {
            $meta['end_reason'] = $reason;
        }
        $session->update([
            'status' => TripTrackingSession::STATUS_ENDED,
            'ended_at' => now(),
            'metadata' => $meta,
        ]);
        return $session->fresh();
    }

    public function cancelSession(TripTrackingSession $session, string $reason): TripTrackingSession
    {
        if (! $session->isActive()) {
            return $session;
        }
        $meta = $session->metadata ?? [];
        $meta['cancellation_reason'] = $reason;
        $session->update([
            'status' => TripTrackingSession::STATUS_CANCELLED,
            'ended_at' => now(),
            'metadata' => $meta,
        ]);
        return $session->fresh();
    }

    /**
     * Récupère la session active courante pour un booking (vue client).
     */
    public function activeSessionForBooking(int $bookingId): ?TripTrackingSession
    {
        return TripTrackingSession::query()
            ->where('booking_id', $bookingId)
            ->active()
            ->latest('id')
            ->first();
    }

    protected function resolveBookingDestination(Booking $booking): array
    {
        // Schéma CleanUx: bookings.destination_lat/destination_lng
        $lat = $booking->getAttribute('destination_lat')
            ?? data_get($booking, 'address_components.lat')
            ?? data_get($booking, 'matching_snapshot.lat');
        $lng = $booking->getAttribute('destination_lng')
            ?? data_get($booking, 'address_components.lng')
            ?? data_get($booking, 'matching_snapshot.lng');

        return [
            $lat !== null ? (float) $lat : null,
            $lng !== null ? (float) $lng : null,
        ];
    }

    protected function broadcastPing(TripTrackingSession $session, TripTrackingPoint $point): void
    {
        try {
            if (! class_exists(\App\Realtime\RealtimeBroadcastService::class)) {
                return;
            }
            $booking = $session->booking;
            if (! $booking) {
                return;
            }

            // Position broadcast
            $missionLike = $this->makeMissionLikeForBroadcast($session);
            if (! $missionLike) {
                return;
            }

            $realtime = app(\App\Realtime\RealtimeBroadcastService::class);

            $posEvent = new MissionLivePosition(
                mission: $missionLike,
                latitude: (float) $point->lat,
                longitude: (float) $point->lng,
                accuracyMeters: $point->accuracy_m ? (float) $point->accuracy_m : null,
                headingDegrees: $point->heading_deg ? (float) $point->heading_deg : null,
                providerUserId: (int) $session->provider_user_id,
                sequence: $point->client_sequence,
            );
            $realtime->publish($posEvent);

            // ETA broadcast si calculé
            if ($point->eta_seconds !== null) {
                $etaMin = (int) ceil($point->eta_seconds / 60);
                $etaEvent = new MissionLiveEta(
                    mission: $missionLike,
                    etaMinutes: $etaMin,
                    latitude: (float) $point->lat,
                    longitude: (float) $point->lng,
                    sequence: $point->client_sequence,
                );
                $realtime->publish($etaEvent);
            }
        } catch (\Throwable $e) {
            Log::warning('[trip_tracking] broadcast failed', [
                'session_id' => $session->id,
                'error' => $e->getMessage(),
            ]);
        }
    }

    /**
     * Adapte session pour event broadcast — utilise Mission si la classe existe et la session relate
     * à une mission, sinon utilise le booking lui-même (broadcast events acceptent any model avec ->id).
     */
    protected function makeMissionLikeForBroadcast(TripTrackingSession $session): mixed
    {
        try {
            if (class_exists(\App\Models\Mission::class)) {
                $mission = \App\Models\Mission::query()
                    ->where('booking_id', $session->booking_id)
                    ->first();
                if ($mission) {
                    return $mission;
                }
            }
        } catch (\Throwable) {}
        return $session->booking;
    }
}

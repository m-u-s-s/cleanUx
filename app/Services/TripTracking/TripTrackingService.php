<?php

namespace App\Services\TripTracking;

use App\Events\Realtime\MissionLiveEta;
use App\Events\Realtime\MissionLivePosition;
use App\Models\Booking;
use App\Models\Mission;
use App\Models\TripTrackingPoint;
use App\Models\TripTrackingSession;
use App\Models\User;
use App\Realtime\RealtimeBroadcastService;
use App\Services\Geo\RoutingService;
use App\Services\GeolocationV2\DistanceCalculator;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\ValidationException;

/** TripTrackingService — tracking GPS provider→client en mission active. Workflow : 1. */
class TripTrackingService
{
    public function __construct(
        protected DistanceCalculator $distance,
    ) {}

    /** Démarre une session tracking pour un booking. */
    /**
     * @param  array{0: float, 1: float}|null  $destination  Le point visé, quand ce n'est PAS celui
     *                                                       de la réservation. Une course en compte
     *                                                       deux successifs : l'approche vers le
     *                                                       client, puis le trajet vers la dépose.
     *                                                       Détourner la première session en
     *                                                       changeant sa destination effacerait
     *                                                       l'histoire de l'approche — dont on a
     *                                                       besoin pour justifier une attente.
     * @param  array<string, mixed>  $metadata  Ce que ce segment représente (`leg`), pour que les
     *                                          deux se distinguent après coup.
     */
    public function startSession(
        User $provider,
        Booking $booking,
        ?float $startLat = null,
        ?float $startLng = null,
        ?array $destination = null,
        array $metadata = [],
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

        // Snapshot destination depuis booking, sauf si l'appelant en désigne une autre.
        [$destLat, $destLng] = $destination ?? $this->resolveBookingDestination($booking);
        $radiusM = (int) Config::get('trip_tracking.geofence_radius_m', 150);

        $session = TripTrackingSession::query()->create([
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
            'metadata' => $metadata !== [] ? $metadata : null,
        ]);

        $this->tracerLaRoute($session);

        return $session;
    }

    /** LA ROUTE À AFFICHER, calculée UNE FOIS et rangée avec la session. */
    protected function tracerLaRoute(TripTrackingSession $session): void
    {
        if ($session->destination_lat === null || $session->destination_lng === null
            || $session->start_lat === null || $session->start_lng === null) {
            return;
        }

        try {
            $route = app(RoutingService::class)->route(
                (float) $session->start_lat,
                (float) $session->start_lng,
                (float) $session->destination_lat,
                (float) $session->destination_lng,
            );

            $session->forceFill([
                'metadata' => array_merge($session->metadata ?? [], [
                    'route_points' => $route->points,
                    'route_source' => $route->source,
                    'route_distance_m' => $route->distanceMeters,
                ]),
            ])->save();
        } catch (\Throwable $e) {
            Log::warning('[trip_tracking] route non tracée', [
                'session_id' => $session->id,
                'error' => $e->getMessage(),
            ]);
        }
    }

    /**
     * Le tracé servi aux cartes — client et prestataire lisent la MÊME chose.
     *
     * @return array{points: list<array{lat: float, lng: float}>, source: string|null, distance_m: int|null}|null
     */
    public function routePayload(TripTrackingSession $session): ?array
    {
        $points = $session->metadata['route_points'] ?? null;

        if (! is_array($points) || $points === []) {
            return null;
        }

        return [
            'points' => $points,
            'source' => $session->metadata['route_source'] ?? null,
            'distance_m' => $session->metadata['route_distance_m'] ?? null,
        ];
    }

    /** Ajoute un point GPS à la session active. */
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

        // Audit LOW — aucune position partagée tant que le prestataire est en pause.
        if ($session->is_paused) {
            throw ValidationException::withMessages([
                'session' => ['Le partage de position est en pause.'],
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

    /** Provider démarre la mission (après être arrivé). */
    /** Audit LOW — met en pause le partage de position (confidentialité prestataire). */
    public function pauseSession(TripTrackingSession $session): TripTrackingSession
    {
        if (! $session->isActive()) {
            throw ValidationException::withMessages([
                'session' => ['Session non active.'],
            ]);
        }

        $session->update(['is_paused' => true, 'paused_at' => now()]);

        return $session->fresh();
    }

    /** Reprend le partage de position après une pause. */
    /** REPREND — ET COMPTE LA PAUSE QUI VIENT DE FINIR. */
    public function resumeSession(TripTrackingSession $session): TripTrackingSession
    {
        if (! $session->is_paused) {
            // Reprendre deux fois n'est pas une erreur — double appui, deux appareils — mais
            // recompter le serait : la seconde reprise ajouterait une durée déjà comptée.
            return $session;
        }

        $session->update([
            'is_paused' => false,
            'paused_at' => null,
            'paused_total_seconds' => $session->paused_total_seconds + $this->secondesDePauseEnCours($session),
        ]);

        return $session->fresh();
    }

    /** La durée de la pause en cours, ou zéro si le prestataire n'est pas en pause. */
    protected function secondesDePauseEnCours(TripTrackingSession $session): int
    {
        if (! $session->is_paused || ! $session->paused_at) {
            return 0;
        }

        return (int) abs(now()->diffInSeconds($session->paused_at));
    }

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

    /** Termine la session (manuelle ou auto via booking completion observer). */
    public function endSession(TripTrackingSession $session, ?string $reason = null): TripTrackingSession
    {
        if (in_array($session->status, [TripTrackingSession::STATUS_ENDED, TripTrackingSession::STATUS_CANCELLED], true)) {
            return $session;
        }
        $meta = $session->metadata ?? [];
        if ($reason) {
            $meta['end_reason'] = $reason;
        }
        // UNE PAUSE EN COURS À LA CLÔTURE COMPTE QUAND MÊME.
        $session->update([
            'status' => TripTrackingSession::STATUS_ENDED,
            'ended_at' => now(),
            'is_paused' => false,
            'paused_at' => null,
            'paused_total_seconds' => $session->paused_total_seconds + $this->secondesDePauseEnCours($session),
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

    /** Récupère la session active courante pour un booking (vue client). */
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
        // Schéma Brio: bookings.destination_lat/destination_lng
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
            if (! class_exists(RealtimeBroadcastService::class)) {
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

            $realtime = app(RealtimeBroadcastService::class);

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

    /** Adapte session pour event broadcast — utilise Mission si la classe existe et la session relate à une mission, sinon utilise le booking lui-même (broadcast events acceptent any model avec ->id). */
    protected function makeMissionLikeForBroadcast(TripTrackingSession $session): mixed
    {
        try {
            if (class_exists(Mission::class)) {
                $mission = Mission::query()
                    ->where('booking_id', $session->booking_id)
                    ->first();
                if ($mission) {
                    return $mission;
                }
            }
        } catch (\Throwable) {
        }

        return $session->booking;
    }
}

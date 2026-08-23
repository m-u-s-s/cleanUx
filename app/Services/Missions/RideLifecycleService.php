<?php

namespace App\Services\Missions;

use App\Events\MissionStatusUpdated;
use App\Models\Mission;
use App\Models\TripTrackingSession;
use App\Models\User;
use App\Notifications\MissionStartedNotification;
use App\Services\TripTracking\TripTrackingService;
use App\Support\Domain\BookingStatus;
use App\Support\Domain\MissionStatus;
use Illuminate\Support\Facades\Log;
use RuntimeException;

/** LE SECOND PARCOURS DE MISSION : la course, d'un point à un autre. */
class RideLifecycleService
{
    public function __construct(
        protected MissionLifecycleService $lifecycle,
        protected MissionAssignmentStatusService $assignmentStatusService,
    ) {}

    /**
     * LE CLIENT EST À BORD — la course commence. `arrived → started`, sans code.
     *
     * @throws RuntimeException si la mission n'est pas une course, ou pas au bon stade
     */
    public function demarrerLaCourse(Mission $mission, User $user, ?float $lat = null, ?float $lng = null): Mission
    {
        $this->assertCourse($mission);
        $this->assignmentStatusService->assertAssignedToMission($mission, $user);

        if (! in_array($mission->status, MissionStatus::canStart(), true)) {
            throw new RuntimeException(
                'Signalez d’abord votre arrivée au point de prise en charge.'
            );
        }

        $mission->update([
            'status' => MissionStatus::STARTED,
            'actual_start_at' => now(),
            'started_by_user_id' => $user->id,
            // La présence du client EST attestée : il est monté dans le véhicule, et le conducteur l'affirme au moment où ça se produit.
            'client_presence_confirmed' => true,
            'start_lat' => $lat ?? $mission->start_lat,
            'start_lng' => $lng ?? $mission->start_lng,
        ]);

        event(new MissionStatusUpdated($mission));

        $this->assignmentStatusService->updateAssignmentStatus($mission, $user, 'arrived', [
            'accepted_at' => now(),
        ]);

        $this->ouvrirLeSuiviVersLeSecondPoint($mission, $user, $lat, $lng);

        if ($mission->booking?->client) {
            $mission->booking->client->notify(new MissionStartedNotification($mission));
        }

        app(MissionHistoryService::class)->log(
            $mission->fresh(),
            $user,
            'ride_started',
            'Course démarrée',
            'Le client est à bord ; le trajet vers le point d’arrivée a commencé.'
        );

        return $mission->fresh(['assignments', 'booking']);
    }

    /**
     * ARRIVÉ À DESTINATION — la course se termine.
     *
     * @throws RuntimeException si la mission n'est pas une course, ou pas démarrée
     */
    public function terminerLaCourse(Mission $mission, User $user, ?float $lat = null, ?float $lng = null): Mission
    {
        $this->assertCourse($mission);

        if (! in_array($mission->status, MissionStatus::canFinish(), true)
            && $mission->status !== MissionStatus::COMPLETED) {
            throw new RuntimeException('La course doit avoir démarré avant d’être terminée.');
        }

        $this->fermerLesSuivis($mission, $lat, $lng);

        // TOUT L'ARGENT PASSE PAR LA CLÔTURE COMMUNE.
        return $this->lifecycle->completeMission($mission, $user, $lat, $lng);
    }

    /** Cette mission relève-t-elle bien du parcours course ? */
    public function estUneCourse(Mission $mission): bool
    {
        return (bool) $mission->booking?->estUneCourse();
    }

    /**
     * @throws RuntimeException
     */
    private function assertCourse(Mission $mission): void
    {
        if (! $this->estUneCourse($mission)) {
            throw new RuntimeException(
                'Cette mission n’est pas une course : elle suit le parcours terrain, avec ses codes de début et de fin.'
            );
        }
    }

    /** LE SECOND SEGMENT DU SUIVI : de A vers B. */
    private function ouvrirLeSuiviVersLeSecondPoint(Mission $mission, User $user, ?float $lat, ?float $lng): void
    {
        $reservation = $mission->booking;

        if (! $reservation) {
            return;
        }

        try {
            $suivi = app(TripTrackingService::class);

            foreach (TripTrackingSession::query()->where('booking_id', $reservation->id)->active()->get() as $session) {
                $suivi->endSession($session, 'Arrivée au point de prise en charge.');
            }

            $suivi->startSession(
                $user,
                $reservation,
                $lat,
                $lng,
                destination: [(float) $reservation->dropoff_lat, (float) $reservation->dropoff_lng],
                metadata: ['leg' => 'ride'],
            );
        } catch (\Throwable $e) {
            Log::warning('[course] suivi du second segment non ouvert', [
                'mission_id' => $mission->id,
                'error' => $e->getMessage(),
            ]);
        }
    }

    /** Referme ce qui reste ouvert : une session vivante après la course fausserait les tableaux. */
    private function fermerLesSuivis(Mission $mission, ?float $lat, ?float $lng): void
    {
        $reservation = $mission->booking;

        if (! $reservation) {
            return;
        }

        try {
            $suivi = app(TripTrackingService::class);

            foreach (TripTrackingSession::query()->where('booking_id', $reservation->id)->active()->get() as $session) {
                if ($lat !== null && $lng !== null) {
                    $session->forceFill(['last_lat' => $lat, 'last_lng' => $lng])->save();
                }

                $suivi->endSession($session, 'Course terminée.');
            }
        } catch (\Throwable $e) {
            Log::warning('[course] suivi non refermé', [
                'mission_id' => $mission->id,
                'error' => $e->getMessage(),
            ]);
        }
    }

    /** Le statut de la réservation suit la course, comme il suit une intervention. */
    public function statutDeReservationPour(string $statutMission): string
    {
        return match ($statutMission) {
            MissionStatus::STARTED => BookingStatus::SUR_PLACE,
            default => BookingStatus::EN_ROUTE,
        };
    }
}

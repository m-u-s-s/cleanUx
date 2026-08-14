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

/**
 * LE SECOND PARCOURS DE MISSION : la course, d'un point à un autre.
 *
 * Le parcours terrain de la plateforme suppose un lieu unique et deux codes à six chiffres — un que
 * le client donne à l'arrivée, un autre à la fin. C'est juste pour un ménage : les deux personnes
 * sont face à face, et le code atteste de cette rencontre.
 *
 * UNE COURSE NE MARCHE PAS COMME ÇA, et aucune plateforme de transport ne fonctionne ainsi. Le
 * client monte dans la voiture — c'est le démarrage ; on arrive à destination — c'est la fin. La
 * preuve n'est pas un code, c'est la trace GPS de A à B, que le client a suivie en direct sur sa
 * carte. Demander six chiffres à quelqu'un qui vient de s'asseoir à l'arrière ajouterait un geste
 * que personne ne fait ailleurs, et que le conducteur contournerait dès la première pluie.
 *
 * CE SERVICE NE DUPLIQUE RIEN. Les gardes d'assignation, l'historique, et surtout `completeMission()`
 * — la capture du paiement, la commission, la ligne de versement, le passage de la réservation à
 * `termine` qui débloque l'avis — restent dans {@see MissionLifecycleService}. Deux chemins d'argent
 * pour une même clôture, c'est ainsi qu'on finit par payer deux fois.
 *
 * CE QU'IL NE TOUCHE PAS : le parcours classique. Les deux ne peuvent pas se croiser, et chaque
 * porte refuse explicitement ce qui n'est pas pour elle — un 409 qui dit pourquoi, jamais un
 * silence.
 */
class RideLifecycleService
{
    public function __construct(
        protected MissionLifecycleService $lifecycle,
        protected MissionAssignmentStatusService $assignmentStatusService,
    ) {}

    /**
     * LE CLIENT EST À BORD — la course commence.
     *
     * `arrived → started`, sans code. C'est le geste qu'un conducteur fait d'une main en démarrant,
     * et le seul instant où il peut le faire.
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
            /*
             * La présence du client EST attestée : il est monté dans le véhicule, et le conducteur
             * l'affirme au moment où ça se produit. Laisser ce drapeau à faux ferait passer toutes
             * les courses pour des interventions sans client dans les tableaux de bord qualité.
             */
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
     * Sans code, et sans checklist : il n'y a rien à cocher sur un trajet. La position est
     * confrontée au point de DÉPOSE — c'est `MissionLifecycleService::lieuDeCloture()` qui le sait,
     * et c'est ce qui empêche de clôturer une course depuis l'autre bout de la ville.
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

        /*
         * TOUT L'ARGENT PASSE PAR LA CLÔTURE COMMUNE. Capture Stripe, commission, ligne de
         * versement, portefeuille, passage de la réservation à `termine` — donc avis client
         * possible — et retour de la présence du prestataire à « en ligne ». Réécrire cette suite
         * ici en produirait une seconde version, et c'est exactement ainsi qu'une plateforme finit
         * par payer deux fois la même course.
         */
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

    /**
     * LE SECOND SEGMENT DU SUIVI : de A vers B.
     *
     * L'approche — le prestataire qui vient chercher le client — est déjà suivie, avec le point de
     * prise en charge pour destination. La course est un AUTRE déplacement, vers un AUTRE point :
     * détourner la première session en changeant sa destination effacerait l'histoire de
     * l'approche, dont on a besoin pour justifier un retard ou une attente.
     *
     * Soft-fail : le suivi est un confort d'exécution, pas une condition. Une course doit pouvoir
     * démarrer même si le module de suivi est indisponible — le client attend dans la voiture.
     */
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

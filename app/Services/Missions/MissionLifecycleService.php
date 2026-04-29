<?php

namespace App\Services\Missions;

use App\Models\Mission;
use App\Models\RendezVous;
use App\Models\User;
use App\Notifications\EmployeArriveNotification;
use App\Notifications\EmployeEnRouteNotification;
use App\Notifications\MissionCompletedNotification;
use App\Notifications\MissionStartedNotification;
use App\Support\Domain\MissionStatus;
use RuntimeException;

class MissionLifecycleService
{
    public function __construct(
        protected MissionAssignmentStatusService $assignmentStatusService,
        protected MissionVerificationCodeService $verificationCodeService,
        protected MissionFromRendezVousSyncService $missionFromRendezVousSyncService,
        protected MissionTrackingService $missionTrackingService,
        protected MissionQualityService $missionQualityService,
    ) {}

    protected function assertRequiredChecklistCompleted(Mission $mission): void
    {
        $mission->loadMissing('checklists.items');

        $missingRequiredItems = $mission->checklists
            ->flatMap(fn($checklist) => $checklist->items)
            ->filter(fn($item) => $item->is_required && $item->status !== 'done');

        if ($missingRequiredItems->isNotEmpty()) {
            throw new \RuntimeException(
                'Impossible de terminer la mission : certaines tâches obligatoires ne sont pas cochées.'
            );
        }
    }

    public function createFromRendezVous(RendezVous $rendezVous): Mission
    {
        return $this->missionFromRendezVousSyncService->createFromRendezVous($rendezVous);
    }

    public function setEnRoute(Mission $mission, User $user): Mission
    {
        $this->assignmentStatusService->assertAssignedToMission($mission, $user);

        $mission->update([
            'status' => MissionStatus::EN_ROUTE,
        ]);

        $this->assignmentStatusService->updateAssignmentStatus($mission, $user, 'accepted', [
            'accepted_at' => now(),
        ]);

        $mission = $mission->fresh(['assignments', 'rendezVous.client', 'leadEmployee']);

        if ($mission->rendezVous?->client) {
            $mission->rendezVous->client->notify(new EmployeEnRouteNotification($mission));
        }

        app(\App\Services\Notifications\SmsService::class)->send(
            $mission->rendezVous?->client?->phone ?? $mission->rendezVous?->telephone_client,
            'CleanUx : votre employé est en route. Vous pouvez suivre sa position depuis votre espace client.'
        );

        app(MissionHistoryService::class)->log(
            $mission->fresh(),
            $user,
            'mission_en_route',
            'Employé en route',
            'Le trajet vers le client a commencé.'
        );

        return $mission;
    }

    public function setArrived(Mission $mission, User $user, ?float $lat = null, ?float $lng = null): Mission
    {
        $this->assignmentStatusService->assertAssignedToMission($mission, $user);

        $this->missionTrackingService->stopActiveForMission($mission, $lat, $lng);

        $mission->update([
            'status' => MissionStatus::ARRIVED,
            'start_lat' => $lat,
            'start_lng' => $lng,
        ]);

        $this->assignmentStatusService->updateAssignmentStatus($mission, $user, 'arrived', [
            'arrived_at' => now(),
        ]);

        $generated = $this->verificationCodeService->createVerificationCode($mission, 'start');
        session()->put('mission_start_code_' . $mission->id, $generated['code']);
        app(\App\Services\Notifications\SmsService::class)->send(
            $mission->rendezVous?->client?->phone ?? $mission->rendezVous?->telephone_client,
            'CleanUx : votre employé est arrivé. Code de début : ' . $generated['code']
        );

        $mission = $mission->fresh(['assignments', 'verificationCodes', 'rendezVous.client', 'leadEmployee']);

        if ($mission->rendezVous?->client) {
            $mission->rendezVous->client->notify(
                new EmployeArriveNotification($mission, $generated['code'])
            );
        }

        app(MissionHistoryService::class)->log(
            $mission->fresh(),
            $user,
            'mission_arrived',
            'Employé arrivé',
            'L’employé est arrivé sur place.'
        );

        return $mission;
    }

    public function generateStartCode(Mission $mission): array
    {
        return $this->verificationCodeService->createVerificationCode($mission, 'start');
    }

    public function validateStartCode(Mission $mission, User $user, string $plainCode, ?float $lat = null, ?float $lng = null): Mission
    {
        $this->assignmentStatusService->assertAssignedToMission($mission, $user);

        $this->verificationCodeService->consumeValidCode($mission, 'start', $plainCode, $user);

        $mission->update([
            'status' => MissionStatus::STARTED,
            'actual_start_at' => now(),
            'started_by_user_id' => $user->id,
            'client_presence_confirmed' => true,
            'start_lat' => $lat ?? $mission->start_lat,
            'start_lng' => $lng ?? $mission->start_lng,
        ]);

        $this->assignmentStatusService->updateAssignmentStatus($mission, $user, 'arrived', [
            'accepted_at' => now(),
        ]);

        if ($mission->rendezVous?->client) {
            $mission->rendezVous->client->notify(new MissionStartedNotification($mission));
        }

        app(MissionHistoryService::class)->log(
            $mission->fresh(),
            $user,
            'mission_started',
            'Mission démarrée',
            'La mission a démarré avec validation client.'
        );

        return $mission->fresh(['verificationCodes', 'assignments']);
    }

    public function generateEndCode(Mission $mission): array
    {
        if (! in_array($mission->status, MissionStatus::canFinish(), true)) {
            throw new RuntimeException('La mission doit être démarrée avant de générer un code de fin.');
        }

        return $this->verificationCodeService->createVerificationCode($mission, 'end');
    }

    public function validateEndCode(Mission $mission, User $user, string $plainCode, ?float $lat = null, ?float $lng = null): Mission
    {
        $this->assignmentStatusService->assertAssignedToMission($mission, $user);

        $this->verificationCodeService->consumeValidCode($mission, 'end', $plainCode, $user);

        return $this->completeMission($mission, $user, $lat, $lng);
    }

    public function validateStartCodeFromQr(Mission $mission, User $user, ?float $lat = null, ?float $lng = null): Mission
    {
        $this->assignmentStatusService->assertAssignedToMission($mission, $user);

        $mission->update([
            'status' => MissionStatus::STARTED,
            'actual_start_at' => now(),
            'started_by_user_id' => $user->id,
            'client_presence_confirmed' => true,
            'start_lat' => $lat ?? $mission->start_lat,
            'start_lng' => $lng ?? $mission->start_lng,
        ]);

        $this->assignmentStatusService->updateAssignmentStatus($mission, $user, 'arrived', [
            'accepted_at' => now(),
        ]);

        if ($mission->rendezVous?->client) {
            $mission->rendezVous->client->notify(new MissionStartedNotification($mission));
        }

        app(MissionHistoryService::class)->log(
            $mission->fresh(),
            $user,
            'mission_started_qr',
            'Mission démarrée via QR code',
            'La mission a démarré après scan du QR code client.'
        );

        return $mission->fresh(['verificationCodes', 'assignments']);
    }

    public function completeMission(Mission $mission, User $user, ?float $lat = null, ?float $lng = null): Mission
    {
        $mission = app(\App\Services\Missions\MissionProfitService::class)
            ->calculate($mission);
        $this->assignmentStatusService->assertAssignedToMission($mission, $user);
        $this->assertRequiredChecklistCompleted($mission);

        $mission->update([
            'status' => MissionStatus::COMPLETED,
            'actual_end_at' => now(),
            'closed_by_user_id' => $user->id,
            'end_lat' => $lat,
            'end_lng' => $lng,
        ]);

        $this->assignmentStatusService->updateAssignmentStatus($mission, $user, 'completed', [
            'completed_at' => now(),
        ]);

        $mission = $mission->fresh(['assignments', 'verificationCodes', 'rendezVous.client', 'leadEmployee']);
        if ($mission->rendezVous) {
            app(\App\Services\Payments\MissionPaymentService::class)
                ->capture($mission->rendezVous);
        }

        if ($mission->rendezVous?->client) {
            $mission->rendezVous->client->notify(new MissionCompletedNotification($mission));
        }
        app(\App\Services\Notifications\SmsService::class)->send(
            $mission->rendezVous?->client?->phone ?? $mission->rendezVous?->telephone_client,
            'CleanUx : votre mission est terminée. Merci de laisser votre avis depuis votre espace client.'
        );

        $mission = $this->missionQualityService->refreshMissionQuality($mission->fresh());
        $this->missionQualityService->generateOrRefreshReport($mission, $user);

        app(MissionHistoryService::class)->log(
            $mission->fresh(),
            $user,
            'mission_completed',
            'Mission terminée',
            'La mission a été clôturée avec validation client.'
        );

        $reportPath = app(\App\Services\Missions\MissionReportService::class)
            ->generate($mission);

        $mission->update([
            'report_path' => $reportPath,
        ]);

        return $mission->fresh(['assignments', 'verificationCodes']);
    }

    public function syncFromRendezVous(RendezVous $rendezVous): Mission
    {
        return $this->missionFromRendezVousSyncService->syncFromRendezVous($rendezVous);
    }
}

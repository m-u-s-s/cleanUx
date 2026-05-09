<?php

namespace App\Services\Missions;

use App\Models\Mission;
use App\Models\Booking;
use App\Services\Geocoding\GeocodingService;
use App\Support\Domain\MissionStatus;
use Illuminate\Support\Facades\DB;

class MissionFromRendezVousSyncService
{
    public function __construct(
        protected MissionAssignmentStatusService $assignmentStatusService,
        protected MissionChecklistService $missionChecklistService,
        protected GeocodingService $geocodingService,
    ) {}

    public function createFromRendezVous(Booking $rendezVous): Mission
    {
        return DB::transaction(function () use ($rendezVous) {
            $mission = Mission::query()->firstOrCreate(
                ['rendez_vous_id' => $rendezVous->id],
                [
                    'organization_account_id' => $rendezVous->organization_account_id,
                    'organization_site_id' => $rendezVous->organization_site_id,
                    'service_catalog_id' => $rendezVous->service_catalog_id,
                    'service_zone_id' => $rendezVous->service_zone_id,
                    'lead_employee_id' => $rendezVous->employe_id,
                    'status' => MissionStatus::initialFor((bool) $rendezVous->employe_id),
                    'mission_type' => $rendezVous->organization_account_id ? 'enterprise' : 'standard',
                    'planned_start_at' => $this->combineDateAndTime($rendezVous->date, $rendezVous->heure),
                    'planned_end_at' => $this->combineDateAndTime(
                        $rendezVous->date,
                        $this->addMinutesToTime($rendezVous->heure, (int) ($rendezVous->duree_estimee ?? $rendezVous->duree ?? 0))
                    ),
                    'requires_start_code' => true,
                    'requires_end_code' => true,
                    'notes' => $rendezVous->commentaire_client,
                ]
            );

            if ($rendezVous->employe_id) {
                $this->assignmentStatusService->syncLeadAssignment($mission, $rendezVous->employe_id);
            }

            if ($mission->status === 'planned' && ! $mission->assignments()->exists()) {
                app(\App\Services\Dispatch\MissionDispatchService::class)
                    ->dispatchToNextProvider($mission);
            }

            return $mission->fresh(['assignments', 'rendezVous']);
        });
    }



    public function syncFromRendezVous(Booking $rendezVous): Mission
    {
        return DB::transaction(function () use ($rendezVous) {
            $plannedStartAt = $this->combineDateAndTime($rendezVous->date, $rendezVous->heure);
            $plannedEndAt = $this->combineDateAndTime(
                $rendezVous->date,
                $this->addMinutesToTime($rendezVous->heure, (int) ($rendezVous->duree_estimee ?? $rendezVous->duree ?? 0))
            );

            $countryCode = strtoupper((string) (
                $rendezVous->postalCode?->country?->iso_code
                ?? data_get($rendezVous->zone_snapshot, 'postal_code.country_code')
                ?? 'BE'
            ));

            $destination = $this->geocodingService->resolve(
                $rendezVous->adresse,
                $rendezVous->code_postal,
                $rendezVous->ville,
                $countryCode
            );

            /** @var Mission $mission */
            $mission = Mission::query()->updateOrCreate(
                ['rendez_vous_id' => $rendezVous->id],
                [
                    'organization_account_id' => $rendezVous->organization_account_id,
                    'organization_site_id' => $rendezVous->organization_site_id,
                    'service_catalog_id' => $rendezVous->service_catalog_id,
                    'service_zone_id' => $rendezVous->service_zone_id,
                    'lead_employee_id' => $rendezVous->employe_id,
                    'status' => MissionStatus::initialFor((bool) $rendezVous->employe_id),
                    'mission_type' => $rendezVous->organization_account_id ? 'enterprise' : 'standard',
                    'planned_start_at' => $plannedStartAt,
                    'planned_end_at' => $plannedEndAt,
                    'destination_lat' => $destination['lat'] ?? null,
                    'destination_lng' => $destination['lng'] ?? null,
                    'notes' => $rendezVous->commentaire_client,
                ]
            );

            if ($rendezVous->employe_id) {
                $this->assignmentStatusService->syncLeadAssignment($mission, $rendezVous->employe_id);
            }

            $this->missionChecklistService->ensureChecklist($mission);

            return $mission->fresh(['assignments', 'rendezVous']);
        });
    }

    protected function combineDateAndTime($date, $time): ?string
    {
        if (! $date || ! $time) {
            return null;
        }

        return date('Y-m-d H:i:s', strtotime((string) $date . ' ' . substr((string) $time, 0, 8)));
    }

    protected function addMinutesToTime($time, int $minutes): ?string
    {
        if (! $time) {
            return null;
        }

        return date('H:i:s', strtotime(substr((string) $time, 0, 8) . ' +' . $minutes . ' minutes'));
    }
}

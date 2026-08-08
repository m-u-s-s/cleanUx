<?php

namespace App\Services\Missions;

use App\Models\Booking;
use App\Models\Mission;
use App\Services\Contracts\ContractSlaService;
use App\Services\Dispatch\MissionDispatchService;
use App\Services\Geocoding\GeocodingService;
use App\Services\Organizations\ProviderOrganisationResolver;
use App\Support\Domain\MissionStatus;
use Illuminate\Support\Facades\DB;

class MissionFromRendezVousSyncService
{
    public function __construct(
        protected MissionAssignmentStatusService $assignmentStatusService,
        protected MissionChecklistService $missionChecklistService,
        protected GeocodingService $geocodingService,
        protected ProviderOrganisationResolver $providerOrganisationResolver,
    ) {}

    /**
     * LA SOCIÉTÉ QUI EXÉCUTE — la décision du booking d'abord, la déduction ensuite.
     *
     * `assigned_provider_organization_id` est une DÉCISION : posée par un dispatch ou par un
     * contrat-cadre. Le profil du salarié n'est qu'une déduction — elle ne doit donc pas l'écraser.
     *
     * `null` reste une réponse valable : la mission d'un indépendant n'appartient à aucune société,
     * et lui en inventer une la ferait apparaître dans le dispatch d'un tiers.
     */
    protected function societeExecutante(Booking $rendezVous): ?int
    {
        $decidee = $rendezVous->assigned_provider_organization_id;

        if ($decidee !== null) {
            return (int) $decidee;
        }

        return $this->providerOrganisationResolver->pourUtilisateur($rendezVous->employe_id);
    }

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
                    // Sans elle, `DispatchCenter` filtre sur NULL et l'espace société reste vide
                    // alors que les missions existent bien.
                    'provider_organization_id' => $this->societeExecutante($rendezVous),
                    'organization_contract_id' => $rendezVous->organization_contract_id,
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
                app(MissionDispatchService::class)
                    ->dispatchToNextProvider($mission);
            }

            if ($mission->organization_contract_id) {
                app(ContractSlaService::class)->armForMission($mission);
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
                    'provider_organization_id' => $this->societeExecutante($rendezVous),
                    'organization_contract_id' => $rendezVous->organization_contract_id,
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

            if ($mission->organization_contract_id) {
                app(ContractSlaService::class)->armForMission($mission);
            }

            return $mission->fresh(['assignments', 'rendezVous']);
        });
    }

    protected function combineDateAndTime($date, $time): ?string
    {
        if (! $date || ! $time) {
            return null;
        }

        $timestamp = strtotime($this->datePart($date).' '.$this->timePart($time));

        // strtotime() rend false sur une entrée ininterprétable, et date(..., false) fabriquait
        // alors 1970-01-01 00:00:00 — hors des bornes d'une colonne TIMESTAMP MySQL, donc rejeté
        // en mode strict (erreur 1292) au milieu du chemin de réservation. planned_start_at étant
        // nullable, null est la valeur sûre : la mission se crée, sans horaire inventé.
        return $timestamp === false ? null : date('Y-m-d H:i:s', $timestamp);
    }

    protected function addMinutesToTime($time, int $minutes): ?string
    {
        if (! $time) {
            return null;
        }

        $timestamp = strtotime($this->timePart($time).' +'.$minutes.' minutes');

        return $timestamp === false ? null : date('H:i:s', $timestamp);
    }

    /**
     * Les colonnes `date` / `heure` peuvent porter une chaîne brute ou un Carbon selon le cast
     * du modèle et le chemin d'écriture : on ramène les deux formes à la même découpe.
     */
    protected function datePart($date): string
    {
        return $date instanceof \DateTimeInterface
            ? $date->format('Y-m-d')
            : substr((string) $date, 0, 10);
    }

    protected function timePart($time): string
    {
        return $time instanceof \DateTimeInterface
            ? $time->format('H:i:s')
            : substr((string) $time, 0, 8);
    }
}

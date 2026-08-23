<?php

namespace App\Services\Missions;

use App\Models\Booking;
use App\Models\InternalAssignmentDecision;
use App\Models\Mission;
use App\Models\OrganizationAccount;
use App\Services\Contracts\ContractSlaService;
use App\Services\Geocoding\GeocodingService;
use App\Services\Organizations\ProviderOrganisationResolver;
use App\Support\Domain\MissionStatus;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class MissionFromRendezVousSyncService
{
    public function __construct(
        protected MissionAssignmentStatusService $assignmentStatusService,
        protected MissionChecklistService $missionChecklistService,
        protected GeocodingService $geocodingService,
        protected ProviderOrganisationResolver $providerOrganisationResolver,
    ) {}

    /** LA SOCIÉTÉ QUI EXÉCUTE — la décision du booking d'abord, la déduction ensuite. */
    protected function societeExecutante(Booking $rendezVous): ?int
    {
        $decidee = $rendezVous->assigned_provider_organization_id;

        if ($decidee !== null) {
            return (int) $decidee;
        }

        return $this->providerOrganisationResolver->pourUtilisateur($rendezVous->employe_id);
    }

    /** L'ÉQUIPE DÉJÀ DÉCIDÉE SUR LE RENDEZ-VOUS, REPORTÉE SUR LA MISSION. */
    protected function equipeExecutante(Booking $rendezVous): ?int
    {
        $equipeId = $rendezVous->provider_team_id;

        if ($equipeId === null) {
            return null;
        }

        $existe = DB::table('provider_teams')->where('id', $equipeId)->exists();

        return $existe ? (int) $equipeId : null;
    }

    /** LE STATUT D'UNE MISSION N'EST PAS UN REFLET DE LA RÉSERVATION — c'est sa vie propre. */
    protected function statutASynchroniser(?Mission $existante, Booking $rendezVous): string
    {
        $initial = MissionStatus::initialFor((bool) $rendezVous->employe_id);

        if ($existante === null) {
            return $initial;
        }

        $courant = (string) $existante->status;

        return in_array($courant, [MissionStatus::PLANNED, MissionStatus::ASSIGNED], true)
            ? $initial
            : $courant;
    }

    public function createFromRendezVous(Booking $rendezVous): Mission
    {
        return DB::transaction(function () use ($rendezVous) {
            // `booking_id` : la seconde clé de `missions` a été supprimée. Ce point d'entrée-ci
            // avait échappé à la fusion et cherchait encore sur la colonne disparue.
            $mission = Mission::query()->firstOrCreate(
                ['booking_id' => $rendezVous->id],
                [
                    'organization_account_id' => $rendezVous->organization_account_id,
                    'organization_site_id' => $rendezVous->organization_site_id,
                    'service_catalog_id' => $rendezVous->service_catalog_id,
                    'service_zone_id' => $rendezVous->service_zone_id,
                    'lead_employee_id' => $rendezVous->employe_id,
                    // Sans elle, `DispatchCenter` filtre sur NULL et l'espace société reste vide
                    // alors que les missions existent bien.
                    'provider_organization_id' => $this->societeExecutante($rendezVous),
                    'provider_team_id' => $this->equipeExecutante($rendezVous),
                    'organization_contract_id' => $rendezVous->organization_contract_id,
                    'status' => MissionStatus::initialFor((bool) $rendezVous->employe_id),
                    'mission_type' => $rendezVous->organization_account_id ? 'enterprise' : 'standard',
                    'planned_start_at' => $this->combineDateAndTime($rendezVous->date, $rendezVous->heure),
                    // Même règle que dans `syncFromRendezVous()` : les deux points créent des
                    // missions, et l'un des deux gardait le repli à zéro.
                    'planned_end_at' => $this->combineDateAndTime(
                        $rendezVous->date,
                        $this->addMinutesToTime($rendezVous->heure, $this->dureeEstimee($rendezVous))
                    ),
                    'requires_start_code' => true,
                    'requires_end_code' => true,
                    'notes' => $rendezVous->commentaire_client,
                ]
            );

            if ($rendezVous->employe_id) {
                $this->assignmentStatusService->syncLeadAssignment($mission, $rendezVous->employe_id);
            }

            // LA MISSION NE SE DISPATCHE PLUS ELLE-MEME.

            if ($mission->organization_contract_id) {
                app(ContractSlaService::class)->armForMission($mission);
            }

            $this->tenterLAutoAssignation($mission);

            return $mission->fresh(['assignments', 'booking']);
        });
    }

    public function syncFromRendezVous(Booking $rendezVous): Mission
    {
        return DB::transaction(function () use ($rendezVous) {
            $plannedStartAt = $this->combineDateAndTime($rendezVous->date, $rendezVous->heure);
            $plannedEndAt = $this->combineDateAndTime(
                $rendezVous->date,
                $this->addMinutesToTime($rendezVous->heure, $this->dureeEstimee($rendezVous))
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

            // CE QUI EST DÉJÀ DÉCIDÉ SUR LA MISSION NE SE DÉDUIT PAS À NOUVEAU.
            $existante = Mission::query()->where('booking_id', $rendezVous->id)->latest('id')->first();

            /** @var Mission $mission */
            // LA CLÉ D'UNICITÉ EST `booking_id`, la seule que porte encore `missions`.
            $mission = Mission::query()->updateOrCreate(
                ['booking_id' => $rendezVous->id],
                [
                    'organization_account_id' => $rendezVous->organization_account_id,
                    'organization_site_id' => $rendezVous->organization_site_id,
                    'service_catalog_id' => $rendezVous->service_catalog_id,
                    'service_zone_id' => $rendezVous->service_zone_id,
                    'lead_employee_id' => $rendezVous->employe_id,
                    'provider_organization_id' => $this->societeExecutante($rendezVous)
                        ?? $existante?->provider_organization_id,
                    'provider_team_id' => $this->equipeExecutante($rendezVous)
                        ?? $existante?->provider_team_id,
                    'organization_contract_id' => $rendezVous->organization_contract_id,
                    'status' => $this->statutASynchroniser($existante, $rendezVous),
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

            $this->tenterLAutoAssignation($mission);

            return $mission->fresh(['assignments', 'booking']);
        });
    }

    /** LE MODE CONTINU — « toute nouvelle mission de la société est auto-assignée ». */
    protected function tenterLAutoAssignation(Mission $mission): void
    {
        if ($mission->provider_organization_id === null || $mission->lead_provider_user_id !== null) {
            return;
        }

        try {
            $actif = (bool) OrganizationAccount::query()
                ->whereKey($mission->provider_organization_id)
                ->value('auto_assign_enabled');

            if (! $actif) {
                return;
            }

            app(InternalDispatchRunner::class)->traiter(
                $mission,
                InternalAssignmentDecision::MODE_AUTO_MODE,
            );
        } catch (\Throwable $e) {
            Log::warning('Auto-assignation impossible sur une mission naissante', [
                'mission_id' => $mission->id,
                'raison' => $e->getMessage(),
            ]);
        }
    }

    /** COMBIEN DE TEMPS CETTE INTERVENTION VA-T-ELLE DURER ? */
    protected function dureeEstimee(Booking $rendezVous): int
    {
        $candidats = [
            (int) ($rendezVous->duree_estimee ?? 0),
            (int) ($rendezVous->estimated_duration_minutes ?? 0),
            (int) ($rendezVous->duree ?? 0),
            (int) ($rendezVous->trade_id ? ($rendezVous->trade->estimated_duration_min ?? 0) : 0),
            (int) Config::get('order_engine.default_duration_minutes', 60),
        ];

        foreach ($candidats as $minutes) {
            if ($minutes > 0) {
                return $minutes;
            }
        }

        return 60;
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

    /** Les colonnes `date` / `heure` peuvent porter une chaîne brute ou un Carbon selon le cast du modèle et le chemin d'écriture : on ramène les deux formes à la même découpe. */
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

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

    /**
     * L'ÉQUIPE DÉJÀ DÉCIDÉE SUR LE RENDEZ-VOUS, REPORTÉE SUR LA MISSION.
     *
     * `bookings.provider_team_id` et `missions.provider_team_id` existent des deux côtés, et le
     * report ne se faisait pas : la mission naissait sans équipe alors que la décision était prise.
     *
     * ON NE DÉDUIT RIEN ICI. Aucun repli par le profil du salarié, aucune équipe « par défaut » :
     * ce champ ne fait que transporter une décision existante. `provider_teams` est par ailleurs
     * gelée — le lot 3 fait de `field_teams` la notion canonique via une colonne distincte. Ce
     * report n'ajoute donc aucun LECTEUR à la table gelée, il cesse seulement de perdre une donnée.
     *
     * L'EXISTENCE EST VÉRIFIÉE, et pas par excès de prudence : `missions.provider_team_id` porte une
     * clé étrangère sur `provider_teams`. Un identifiant périmé sur un vieux rendez-vous ferait
     * échouer la création de mission en MySQL — au milieu du parcours de réservation, et sans que la
     * suite le voie, SQLite n'appliquant pas toujours les clés étrangères. Voir
     * [[test-suite-sqlite-blindness]].
     */
    protected function equipeExecutante(Booking $rendezVous): ?int
    {
        $equipeId = $rendezVous->provider_team_id;

        if ($equipeId === null) {
            return null;
        }

        $existe = DB::table('provider_teams')->where('id', $equipeId)->exists();

        return $existe ? (int) $equipeId : null;
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

            /*
             * LA MISSION NE SE DISPATCHE PLUS ELLE-MEME.
             *
             * Elle appelait ici `dispatchToNextProvider()` a sa naissance. C'etait la SECONDE
             * porte amont : le moteur de commande ouvrait sa recherche par rayons d'un cote, la
             * mission lancait sa chaine d'offres de l'autre, et la meme course sortait par les
             * deux — deux prestataires se deplacaient pour une seule intervention.
             *
             * Le dispatch a desormais UNE porte : `DispatchEngine::dispatchBooking()`, appelee a
             * la confirmation. La mission n'est plus qu'un dossier d'execution, ce qu'elle a
             * toujours ete.
             */

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

            /*
             * CE QUI EST DÉJÀ DÉCIDÉ SUR LA MISSION NE SE DÉDUIT PAS À NOUVEAU.
             *
             * `societeExecutante()` et `equipeExecutante()` répondent `null` quand la réservation ne
             * porte pas la décision — cas normal d'un indépendant. Mais la mission, elle, peut
             * l'avoir reçue par ailleurs : une société prestataire assignée par le dispatch interne,
             * une équipe posée à la main. Réécrire `null` par-dessus effaçait cette décision au
             * premier enregistrement de la réservation, et la mission sortait alors du périmètre de
             * sa société — ses renforts perdaient l'accès, le tableau de bord la perdait de vue.
             *
             * Une déduction absente n'est pas une décision de retirer.
             */
            $existante = Mission::query()->where('booking_id', $rendezVous->id)->latest('id')->first();

            /** @var Mission $mission */
            /*
              * LA CLÉ D'UNICITÉ EST `booking_id`, la seule que porte encore `missions`.
              *
              * Ce service écrivait `rendez_vous_id` pendant que le moteur de commande écrivait
              * `booking_id` : deux chemins créaient chacun leur mission pour la même réservation,
              * sans jamais se voir.
              */
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

            $this->tenterLAutoAssignation($mission);

            return $mission->fresh(['assignments', 'booking']);
        });
    }

    /**
     * LE MODE CONTINU — « toute nouvelle mission de la société est auto-assignée ».
     *
     * Le bouton du gérant traite l'arriéré ; ceci traite le flux. Sans lui, une société qui a activé
     * l'auto-assignation devrait quand même appuyer sur un bouton chaque matin, ce qui n'est pas
     * « automatique ».
     *
     * TROIS CONDITIONS, ET CHACUNE COMPTE : une société identifiée (une mission d'indépendant ne
     * regarde personne), le réglage activé (faux par défaut — aucune société ne se met à distribuer
     * son travail du fait d'un déploiement), et personne d'assigné (un rendez-vous confirmé avec un
     * salarié nommé porte déjà sa décision, la réécrire l'annulerait).
     *
     * SOFT-FAIL. Ce hook vit sur le chemin de la RÉSERVATION : une panne du moteur ne doit pas
     * empêcher un client de réserver. La mission naît sans personne, ce que le dispatch montre —
     * plutôt qu'une erreur au visage du client pour un problème d'organisation interne.
     */
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

    /**
     * COMBIEN DE TEMPS CETTE INTERVENTION VA-T-ELLE DURER ?
     *
     * Le repli était `0` — et c'est ce zéro qui donnait des missions dont la fin tombait à la
     * minute du début. Une fenêtre de durée nulle ne chevauche rien : la détection de conflit ne
     * pouvait plus protéger le créneau du prestataire, et son planning affichait « 10:00 → 10:00 ».
     *
     * L'ordre importe : la durée de la RÉSERVATION d'abord — elle tient compte des réponses, une
     * surface de 80 m² ne prend pas le même temps que 20 —, puis celle déclarée au catalogue pour
     * le métier, et seulement en dernier recours une valeur de configuration. Zéro n'est plus une
     * réponse acceptable.
     */
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

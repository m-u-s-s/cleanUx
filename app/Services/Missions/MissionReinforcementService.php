<?php

namespace App\Services\Missions;

use App\Models\Mission;
use App\Models\MissionReinforcementRequest;
use App\Models\User;
use App\Support\ActivityLogger;
use DomainException;
use Illuminate\Support\Carbon;

/**
 * DEMANDER DU RENFORT DEPUIS LE TERRAIN.
 *
 * ── LA TROISIÈME ISSUE, CELLE QUI MANQUAIT ───────────────────────────────────────────────────
 *
 * Un prestataire qui découvre un chantier deux fois plus gros que prévu n'avait que deux sorties :
 * réviser le devis, ou abandonner. Le renfort est la troisième, et c'est souvent la bonne — le
 * travail se fait, le client garde son intervention, et personne ne renégocie sous pression.
 *
 * C'est aussi pour cela que le questionnaire d'annulation y renvoie : « le chantier est trop gros
 * pour moi seul » n'est pas une annulation.
 *
 * ── POURQUOI CE SERVICE EXISTE À CÔTÉ DE `TeamLeadOperationsService` ─────────────────────────
 *
 * Celui-là demande un `MissionTaskSegment` : c'est le chemin des chantiers découpés en lots, où un
 * chef d'équipe pilote des segments. Un prestataire seul sur une intervention ordinaire n'a aucun
 * segment, et lui en inventer un pour poser une demande créerait une ligne d'exécution fictive dans
 * un module de planification. La table, elle, accepte déjà une demande rattachée à la seule mission.
 */
class MissionReinforcementService
{
    public function __construct(
        private readonly MissionAssignmentStatusService $affectations,
    ) {}

    /**
     * @throws DomainException
     */
    public function demander(
        Mission $mission,
        User $prestataire,
        string $motif,
        int $personnes = 1,
        int $minutes = 60,
    ): MissionReinforcementRequest {
        $this->affectations->assertAssignedToMission($mission, $prestataire);

        if (trim($motif) === '') {
            throw new DomainException('Dites ce qui justifie le renfort : c’est ce que lira celui qui viendra.');
        }

        /*
         * UNE SEULE DEMANDE OUVERTE PAR MISSION.
         *
         * Deux demandes concurrentes feraient venir deux renforts pour le même besoin — et le
         * second se déplacerait pour rien, à la charge de la plateforme.
         */
        $ouverte = MissionReinforcementRequest::query()
            ->where('mission_id', $mission->id)
            ->where('status', 'open')
            ->latest('id')
            ->first();

        if ($ouverte !== null) {
            throw new DomainException('Une demande de renfort est déjà ouverte sur cette mission.');
        }

        $demande = MissionReinforcementRequest::query()->create([
            'mission_id' => $mission->id,
            'requested_by_user_id' => $prestataire->id,
            'provider_team_id' => $mission->provider_team_id,
            'field_team_id' => $mission->field_team_id,
            'status' => 'open',
            'priority' => 'haute',
            'required_people' => max(1, $personnes),
            'requested_members' => max(1, $personnes),
            'requested_minutes' => max(15, $minutes),
            'reason' => trim($motif),
            // MAINTENANT, et pas « dès que possible » : le prestataire est déjà sur place, et une
            // demande sans échéance se traite après celles qui en portent une.
            'needed_at' => Carbon::now(),
        ]);

        ActivityLogger::log('mission.reinforcement_requested', $demande, [
            'mission_id' => $mission->id,
            'personnes' => $demande->required_people,
        ]);

        app(MissionHistoryService::class)->log(
            $mission,
            $prestataire,
            'mission_reinforcement_requested',
            'Renfort demandé',
            trim($motif),
            ['reinforcement_id' => $demande->id],
        );

        return $demande;
    }

    /** La demande qui attend encore quelqu'un, s'il y en a une. */
    public function ouverte(Mission $mission): ?MissionReinforcementRequest
    {
        return MissionReinforcementRequest::query()
            ->where('mission_id', $mission->id)
            ->where('status', 'open')
            ->latest('id')
            ->first();
    }
}

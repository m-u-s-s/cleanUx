<?php

namespace App\Services\Missions;

use App\Enums\OrganizationRole;
use App\Models\FieldTeamMember;
use App\Models\Mission;
use App\Models\OrganizationMember;
use App\Models\User;
use App\Services\PermissionService;

/** QUI PEUT RÉASSIGNER CETTE MISSION ? — l'exigence 5, et sa portée. */
class ReassignmentPolicy
{
    public function __construct(private PermissionService $permissions) {}

    /** Cet utilisateur peut-il réassigner cette mission ? */
    public function peutReassigner(User $utilisateur, Mission $mission): bool
    {
        $organisationId = $mission->provider_organization_id;

        if ($organisationId === null) {
            // La mission d'un indépendant n'appartient à aucune société : personne n'y réassigne
            // quoi que ce soit depuis un espace société.
            return false;
        }

        $membre = OrganizationMember::query()
            ->where('organization_account_id', $organisationId)
            ->where('user_id', $utilisateur->id)
            ->where('status', 'active')
            ->first();

        // L'APPARTENANCE D'ABORD, ET SANS EXCEPTION.
        if ($membre === null) {
            return false;
        }

        // La capacité globale : dispatcheur, directeur d'opérations, propriétaire. Elle vaut pour
        // toutes les missions de la société, et c'est son sens.
        if ($this->permissions->can($utilisateur, 'missions.assign', $organisationId)) {
            return $this->porteeGlobale($membre, $mission);
        }

        // Le MENEUR de l'équipe, même sans rang ni clé.
        return $this->meneDeLEquipeDeLaMission($utilisateur, $mission);
    }

    /** `missions.assign` accordée : reste à savoir jusqu'où elle porte. */
    private function porteeGlobale(OrganizationMember $membre, Mission $mission): bool
    {
        if ($membre->role !== OrganizationRole::TEAM_LEAD) {
            return true;
        }

        $utilisateur = $membre->user;

        if ($utilisateur === null) {
            return false;
        }

        return $this->meneDeLEquipeDeLaMission($utilisateur, $mission)
            || $this->estMembreDeLEquipeDeLaMission($utilisateur, $mission);
    }

    /** L'acteur est-il le meneur déclaré de l'équipe à qui cette mission est confiée ? */
    private function meneDeLEquipeDeLaMission(User $utilisateur, Mission $mission): bool
    {
        $equipe = $mission->fieldTeam;

        return $equipe !== null
            && (int) $equipe->team_lead_user_id === (int) $utilisateur->id
            // Une équipe d'une autre société ne confère rien, même si la mission la référence.
            && (int) $equipe->organization_account_id === (int) $mission->provider_organization_id;
    }

    /** L'acteur fait-il partie de l'équipe à qui cette mission est confiée ? */
    private function estMembreDeLEquipeDeLaMission(User $utilisateur, Mission $mission): bool
    {
        if ($mission->field_team_id === null) {
            // UNE MISSION SANS ÉQUIPE N'EST L'ÉQUIPE DE PERSONNE.
            return false;
        }

        return FieldTeamMember::query()
            ->where('field_team_id', $mission->field_team_id)
            ->where('user_id', $utilisateur->id)
            ->where('is_active', true)
            ->whereNull('left_at')
            ->exists();
    }
}

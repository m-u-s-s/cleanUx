<?php

namespace App\Services\Missions;

use App\Enums\OrganizationRole;
use App\Models\FieldTeamMember;
use App\Models\Mission;
use App\Models\OrganizationMember;
use App\Models\User;
use App\Services\PermissionService;

/**
 * QUI PEUT RÉASSIGNER CETTE MISSION ? — l'exigence 5, et sa portée.
 *
 * « Owner, chef d'équipe et responsables peuvent réassigner une mission entre workers, le chef
 * d'équipe au sein de SON équipe seulement. » La seconde moitié de cette phrase n'est pas
 * exprimable dans une matrice de clés : `missions.assign` ouvre la CAPACITÉ, elle ne borne pas le
 * PÉRIMÈTRE. C'est ce que fait cette classe.
 *
 * ELLE RÉCONCILIE DEUX NOTIONS DE « CHEF D'ÉQUIPE » QUI S'IGNORAIENT :
 *
 *   - `OrganizationRole::TEAM_LEAD` — un rang dans la société, qui ne dit rien de QUELLE équipe ;
 *   - `field_teams.team_lead_user_id` — la personne qui mène une équipe précise, sans rang
 *     particulier dans l'organisation.
 *
 * Les deux existaient, aucun code ne les rapprochait, et prendre l'une pour l'autre donne deux
 * erreurs opposées : un chef d'équipe de rang réassignant les missions de toute la société, ou le
 * meneur d'une équipe incapable de toucher aux siennes.
 *
 * LE PÉRIMÈTRE EST CELUI DE L'ÉQUIPE DE LA MISSION, pas celui des équipes de l'acteur. Un chef
 * d'équipe qui appartient à trois équipes ne peut agir que sur les missions confiées à l'une
 * d'elles — la question est toujours « cette mission-ci est-elle la mienne ? ».
 */
class ReassignmentPolicy
{
    public function __construct(private PermissionService $permissions) {}

    /**
     * Cet utilisateur peut-il réassigner cette mission ?
     */
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

        /*
         * L'APPARTENANCE D'ABORD, ET SANS EXCEPTION.
         *
         * Le rang ne traverse pas les sociétés : un propriétaire est propriétaire CHEZ LUI. La
         * garde est évaluée sur l'organisation de la MISSION, jamais sur celle de qui regarde —
         * même correction que `MissionPolicy` au lot 1.
         */
        if ($membre === null) {
            return false;
        }

        // La capacité globale : dispatcheur, directeur d'opérations, propriétaire. Elle vaut pour
        // toutes les missions de la société, et c'est son sens.
        if ($this->permissions->can($utilisateur, 'missions.assign', $organisationId)) {
            return $this->porteeGlobale($membre, $mission);
        }

        /*
         * Le MENEUR de l'équipe, même sans rang ni clé.
         *
         * `field_teams.team_lead_user_id` désigne quelqu'un qui répond de cette équipe au quotidien.
         * Lui refuser d'échanger deux de ses membres au motif qu'il n'a pas `missions.assign`
         * l'obligerait à appeler le bureau pour un geste qui lui revient.
         */
        return $this->meneDeLEquipeDeLaMission($utilisateur, $mission);
    }

    /**
     * `missions.assign` accordée : reste à savoir jusqu'où elle porte.
     *
     * Pour un `team_lead`, la clé est bornée à SON équipe — c'est la moitié de l'exigence 5 que la
     * matrice ne peut pas exprimer. Pour les autres rôles qui la portent, elle vaut partout dans la
     * société.
     */
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
            /*
             * UNE MISSION SANS ÉQUIPE N'EST L'ÉQUIPE DE PERSONNE.
             *
             * Rendre `true` ici — au motif qu'aucune frontière n'est franchie — donnerait à tout
             * chef d'équipe la main sur l'arriéré non affecté de la société, c'est-à-dire
             * exactement les missions que le répartiteur n'a pas encore attribuées.
             */
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

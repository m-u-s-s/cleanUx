<?php

namespace App\Services\Missions;

use App\Models\Mission;
use App\Models\MissionAssignment;
use App\Models\OrganizationMember;
use Illuminate\Support\Facades\DB;

/**
 * ASSIGNER UNE MISSION À UN TRAVAILLEUR — UNE SEULE FOIS, POUR TOUTES LES SURFACES.
 *
 * Cette logique vivait uniquement dans `DispatchCenter::confirmAssign()`, l'écran web. L'API mobile
 * en avait besoin à son tour : la recopier aurait créé deux versions d'une règle délicate, vouées à
 * diverger au premier ajustement.
 *
 * La règle en question — corrigée en phase 0 — est que RÉASSIGNER, C'EST AUSSI DÉSASSIGNER. On ne
 * créait auparavant que le nouvel assignment : la mission finissait avec deux lignes actives, et
 * `lead_provider_user_id` continuait de désigner le travailleur remplacé. En cascade, le tableau de
 * bord affichait l'ancien, l'autorisation Reverb `mission.{id}` lui restait ouverte et le suivi de
 * trajet le visait encore.
 */
class MissionAssignmentService
{
    /**
     * @param  Mission  $mission  déjà résolu et scopé sur l'organisation par l'appelant
     * @param  OrganizationMember  $travailleur  membre actif de cette même organisation
     */
    public function assigner(Mission $mission, OrganizationMember $travailleur): void
    {
        DB::transaction(function () use ($mission, $travailleur) {
            /*
             * On libère les RESPONSABLES actifs des autres personnes avant d'installer le nouveau.
             * `reassigned` — et non `cancelled` — parce que l'historique doit distinguer un
             * remplacement d'un abandon.
             *
             * LE FILTRE SUR LE RÔLE COMPTE, depuis que les renforts existent (lot 2C). Cette
             * requête libérait TOUS les assignments actifs des autres : correct tant qu'une mission
             * n'avait qu'un seul intervenant possible, dévastateur ensuite — remplacer le
             * responsable la veille aurait silencieusement désassigné toute l'équipe.
             *
             * `orWhereNull` est indispensable et n'est pas de la prudence : `role_on_mission` est
             * une colonne NULLABLE ajoutée après coup, et les lignes antérieures au rôle explicite
             * la portent à `null`. En SQL, `role_on_mission != 'helper'` ne les sélectionne PAS —
             * une comparaison avec NULL vaut NULL, jamais vrai. Sans cette branche, les anciens
             * responsables ne seraient plus jamais libérés.
             */
            MissionAssignment::query()
                ->where('mission_id', $mission->id)
                ->where('user_id', '!=', $travailleur->user_id)
                ->where('assignment_status', 'assigned')
                ->where(fn ($q) => $q
                    ->where('role_on_mission', '!=', 'helper')
                    ->orWhereNull('role_on_mission')
                )
                ->update(['assignment_status' => 'reassigned']);

            MissionAssignment::updateOrCreate(
                ['mission_id' => $mission->id, 'user_id' => $travailleur->user_id],
                ['role_on_mission' => 'lead', 'assignment_status' => 'assigned', 'assigned_at' => now()]
            );

            // `lead_provider_user_id` est la source de vérité lue par le tableau de bord, la
            // diffusion temps réel et le suivi de trajet : la laisser en arrière casse les trois.
            $mission->update([
                'status' => 'assigned',
                'lead_provider_user_id' => $travailleur->user_id,
            ]);
        });
    }

    /**
     * Ajouter un RENFORT à une mission, sans toucher au responsable.
     *
     * Un grand nettoyage à deux est le cas ordinaire d'une société, et il n'était pas
     * représentable : l'écran ne savait qu'assigner une personne en remplaçant la précédente. Les
     * équipes s'organisaient donc hors de l'outil, et le suivi ne voyait qu'une moitié du travail.
     *
     * `lead_provider_user_id` n'est délibérément PAS touché : le responsable reste unique, parce
     * que trois systèmes en dépendent — l'autorisation Reverb `mission.{id}`, le suivi de trajet et
     * l'affichage du tableau de bord. Un renfort intervient ; il ne répond pas de la mission.
     *
     * @param  Mission  $mission  déjà résolu et scopé sur l'organisation par l'appelant
     * @param  OrganizationMember  $renfort  membre actif de cette même organisation
     */
    public function ajouterRenfort(Mission $mission, OrganizationMember $renfort): void
    {
        // Le responsable ne se rétrograde pas en renfort par mégarde : ce serait laisser la mission
        // sans personne pour en répondre, alors que `lead_provider_user_id` continuerait de le
        // désigner.
        if ((int) $mission->lead_provider_user_id === (int) $renfort->user_id) {
            return;
        }

        MissionAssignment::updateOrCreate(
            ['mission_id' => $mission->id, 'user_id' => $renfort->user_id],
            ['role_on_mission' => 'helper', 'assignment_status' => 'assigned', 'assigned_at' => now()]
        );
    }

    /**
     * Retirer un renfort.
     *
     * La ligne SURVIT, au statut `released` : l'historique d'une mission doit pouvoir dire qui y a
     * été affecté, même brièvement — une réclamation client se règle sur ce genre de détail.
     * Supprimer effacerait la trace.
     */
    public function retirerRenfort(Mission $mission, int $userId): void
    {
        MissionAssignment::query()
            ->where('mission_id', $mission->id)
            ->where('user_id', $userId)
            ->where('role_on_mission', 'helper')
            ->update(['assignment_status' => 'released']);
    }
}

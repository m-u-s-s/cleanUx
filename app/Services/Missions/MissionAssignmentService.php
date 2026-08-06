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
             * On libère les leads actifs des AUTRES personnes avant d'installer le nouveau.
             * `reassigned` — et non `cancelled` — parce que l'historique doit distinguer un
             * remplacement d'un abandon.
             */
            MissionAssignment::query()
                ->where('mission_id', $mission->id)
                ->where('user_id', '!=', $travailleur->user_id)
                ->where('assignment_status', 'assigned')
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
}

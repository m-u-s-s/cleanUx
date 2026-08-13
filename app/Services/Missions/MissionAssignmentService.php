<?php

namespace App\Services\Missions;

use App\Models\FieldTeam;
use App\Models\FieldTeamMember;
use App\Models\Mission;
use App\Models\MissionAssignment;
use App\Models\OrganizationMember;
use App\Services\Organizations\OrganizationNotifier;
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
     * @param  ?int  $parUtilisateurId  qui décide — tracé sur les lignes libérées
     * @param  ?string  $motif  pourquoi, s'il a été saisi
     */
    public function assigner(
        Mission $mission,
        OrganizationMember $travailleur,
        ?int $parUtilisateurId = null,
        ?string $motif = null,
    ): void {
        // Qui perd la mission — relevé AVANT la libération, sinon il n'y a plus rien à lire.
        $sortants = $this->responsablesActifsAutresQue($mission, (int) $travailleur->user_id);

        DB::transaction(function () use ($mission, $travailleur, $parUtilisateurId, $motif) {
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
                /*
                 * ON TRACE QUI REMPLACE, ET POURQUOI.
                 *
                 * La ligne libérée disait `reassigned` et rien d'autre. L'intervenant retiré de la
                 * mission de demain découvrait le changement sans savoir à qui s'adresser, et une
                 * réclamation client se réglait sans trace de la décision.
                 */
                ->update([
                    'assignment_status' => 'reassigned',
                    'reassigned_by' => $parUtilisateurId,
                    'reassignment_reason' => $motif,
                ]);

            MissionAssignment::updateOrCreate(
                ['mission_id' => $mission->id, 'user_id' => $travailleur->user_id],
                ['role_on_mission' => 'lead', 'assignment_status' => 'assigned', 'assigned_at' => now()]
            );

            /*
             * LES DEUX COLONNES DU RESPONSABLE, ENSEMBLE.
             *
             * `lead_provider_user_id` est la source de vérité lue par le tableau de bord, la
             * diffusion temps réel et le suivi de trajet : la laisser en arrière casse les trois.
             *
             * `lead_employee_id` nomme la MÊME personne, et n'était pas mise à jour. Tout le
             * terrain web la lit — `MissionPolicy`, les tableaux d'exécution et d'incidents, le
             * suivi de trajet salarié : la mission restait donc au nom de la personne remplacée
             * partout de ce côté, pendant que le mobile affichait déjà la nouvelle.
             */
            $mission->update([
                'status' => 'assigned',
                'lead_provider_user_id' => $travailleur->user_id,
                'lead_employee_id' => $travailleur->user_id,
            ]);

            $this->reporterSurLaReservation($mission, (int) $travailleur->user_id);
        });

        /*
         * PERSONNE N'ÉTAIT PRÉVENU — ni l'entrant, ni le sortant.
         *
         * Quelqu'un se voyait retirer la mission de demain sans le savoir, et l'apprenait en
         * arrivant sur place — ou n'y allait pas. Le remplaçant, lui, la découvrait en ouvrant
         * l'application, s'il l'ouvrait.
         *
         * APRÈS la transaction, jamais dedans : une notification est un effet EXTERNE, et l'envoyer
         * avant le commit annoncerait un changement qu'un rollback annulerait.
         */
        $this->prevenirDeLAssignation($mission, (int) $travailleur->user_id, $sortants, $motif);
    }

    /**
     * LA RÉSERVATION SUIT LA MISSION — sinon rien ne les remet jamais d'accord.
     *
     * `bookings.employe_id` nomme la même personne une troisième fois, et c'est celle que lit tout
     * le web salarié : « Mes rendez-vous », le planning, l'historique, les statistiques, la
     * facturation. La réassignation n'écrivait que la mission : le remplaçant ne voyait donc jamais
     * l'intervention apparaître dans son espace web, et la personne remplacée continuait de l'y
     * voir. `Booking::intervenantId()` répare les lecteurs qu'on peut réécrire ; ceci répare la
     * cause.
     *
     * ET C'EST AUSSI UNE QUESTION D'ARGENT. L'autorisation Stripe est une « destination charge » :
     * elle désigne le compte du prestataire au moment où elle est posée. Tant que la réservation
     * n'était pas touchée, `BookingPaymentDestinationObserver` ne se déclenchait pas — la retenue
     * restait pointée sur la personne remplacée, et l'encaissement la payait, elle. Le refus que
     * l'observateur oppose désormais est le comportement voulu : on libère la retenue d'abord, on
     * réassigne ensuite. Mieux vaut une réassignation qui s'arrête net qu'un virement au mauvais
     * compte.
     */
    private function reporterSurLaReservation(Mission $mission, int $intervenantId): void
    {
        $reservation = $mission->booking;

        if ($reservation === null || (int) $reservation->employe_id === $intervenantId) {
            return;
        }

        // `forceFill` : ces colonnes d'affectation ne sont pas toutes assignables en masse, et un
        // écart silencieux ici redonnerait exactement le défaut qu'on ferme.
        $reservation->forceFill([
            'employe_id' => $intervenantId,
            'assigned_provider_user_id' => $intervenantId,
        ])->save();
    }

    /**
     * Les responsables actifs autres que celui qu'on installe — ceux que l'assignation va libérer.
     *
     * @return list<int>
     */
    private function responsablesActifsAutresQue(Mission $mission, int $entrantId): array
    {
        return MissionAssignment::query()
            ->where('mission_id', $mission->id)
            ->where('user_id', '!=', $entrantId)
            ->where('assignment_status', 'assigned')
            ->where(fn ($q) => $q->where('role_on_mission', '!=', 'helper')->orWhereNull('role_on_mission'))
            ->pluck('user_id')
            ->map(fn ($id) => (int) $id)
            ->values()
            ->all();
    }

    /**
     * @param  list<int>  $sortants
     */
    private function prevenirDeLAssignation(Mission $mission, int $entrantId, array $sortants, ?string $motif): void
    {
        $notifier = app(OrganizationNotifier::class);
        $quand = $mission->planned_start_at?->format('d/m à H:i') ?? 'à une date à préciser';

        $notifier->notifierUtilisateur(
            userId: $entrantId,
            titre: 'Nouvelle mission',
            corps: "Une mission vous est confiée le {$quand}.",
            donnees: ['type' => 'mission_assigned', 'mission_id' => $mission->id],
            // Idempotence par (mission, personne, geste) : deux enregistrements de la même décision
            // ne doivent pas faire vibrer deux fois le même téléphone.
            cleIdempotence: "mission:{$mission->id}:assign:{$entrantId}",
        );

        foreach ($sortants as $sortantId) {
            $notifier->notifierUtilisateur(
                userId: $sortantId,
                titre: 'Mission retirée',
                corps: $motif !== null
                    ? "La mission du {$quand} a été confiée à quelqu'un d'autre : {$motif}"
                    : "La mission du {$quand} a été confiée à quelqu'un d'autre.",
                donnees: ['type' => 'mission_released', 'mission_id' => $mission->id],
                cleIdempotence: "mission:{$mission->id}:release:{$sortantId}",
            );
        }
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

        $dejaPresent = MissionAssignment::query()
            ->where('mission_id', $mission->id)
            ->where('user_id', $renfort->user_id)
            ->where('assignment_status', 'assigned')
            ->exists();

        MissionAssignment::updateOrCreate(
            ['mission_id' => $mission->id, 'user_id' => $renfort->user_id],
            ['role_on_mission' => 'helper', 'assignment_status' => 'assigned', 'assigned_at' => now()]
        );

        /*
         * UNE NOTIFICATION PAR PERSONNE, MÊME SUR UNE ASSIGNATION D'ÉQUIPE.
         *
         * `assignerEquipe()` appelle cette méthode pour chaque membre. Sans la vérification
         * ci-dessus, rebasculer une équipe sur elle-même — geste courant quand on corrige un
         * horaire — ferait vibrer tous les téléphones pour une nouvelle qui n'en est pas une.
         */
        if (! $dejaPresent) {
            app(OrganizationNotifier::class)->notifierUtilisateur(
                userId: (int) $renfort->user_id,
                titre: 'Renfort sur une mission',
                corps: 'Vous intervenez en renfort le '
                    .($mission->planned_start_at?->format('d/m à H:i') ?? 'à une date à préciser').'.',
                donnees: ['type' => 'mission_helper_added', 'mission_id' => $mission->id],
                cleIdempotence: "mission:{$mission->id}:helper:{$renfort->user_id}",
            );
        }
    }

    /**
     * Retirer un renfort.
     *
     * La ligne SURVIT, au statut `released` : l'historique d'une mission doit pouvoir dire qui y a
     * été affecté, même brièvement — une réclamation client se règle sur ce genre de détail.
     * Supprimer effacerait la trace.
     */
    public function retirerRenfort(
        Mission $mission,
        int $userId,
        ?int $parUtilisateurId = null,
        ?string $motif = null,
    ): void {
        MissionAssignment::query()
            ->where('mission_id', $mission->id)
            ->where('user_id', $userId)
            ->where('role_on_mission', 'helper')
            ->update([
                'assignment_status' => 'released',
                'reassigned_by' => $parUtilisateurId,
                'reassignment_reason' => $motif,
            ]);
    }

    /**
     * CONFIER LA MISSION À UNE ÉQUIPE ENTIÈRE, EN UN GESTE.
     *
     * C'est le cas ordinaire d'une société : on n'envoie pas une personne dans un immeuble de dix
     * étages, on y envoie l'équipe Nord. Cela n'était pas représentable — l'écran ne savait
     * qu'assigner un individu à la fois, en remplaçant le précédent, si bien que composer une équipe
     * demandait un responsable puis N renforts, un par un, sans jamais dire QUELLE équipe.
     *
     * LE CHEF D'ÉQUIPE DEVIENT LE RESPONSABLE, avec repli sur le premier membre actif. Une mission
     * sans responsable n'est pas assignée du tout : `lead_provider_user_id` est lu par le tableau de
     * bord, l'autorisation Reverb `mission.{id}` et le suivi de trajet.
     *
     * LES RENFORTS NON REPRIS SONT LIBÉRÉS. Basculer de l'équipe A vers l'équipe B doit retirer les
     * membres de A qui ne sont pas dans B — sans quoi la mission accumulerait les intervenants de
     * toutes les équipes qui y sont passées, et le répartiteur la croirait sur-dotée.
     *
     * @param  Mission  $mission  déjà résolu et scopé sur l'organisation par l'appelant
     * @return bool `false` si rien n'a pu être fait — équipe d'une autre société, ou sans personne
     */
    public function assignerEquipe(
        Mission $mission,
        FieldTeam $equipe,
        ?int $parUtilisateurId = null,
        ?string $motif = null,
    ): bool {
        /*
         * L'ANTI-FUITE ENTRE SOCIÉTÉS, ET ELLE EST ICI PLUTÔT QU'EN CLÉ ÉTRANGÈRE.
         *
         * `missions.field_team_id` n'a pas de contrainte SQL — SQLite ne sait pas en ajouter une par
         * `ALTER TABLE`. Surtout, une FK ne saurait pas exprimer CETTE règle : l'équipe doit
         * appartenir à la société qui exécute la mission, pas seulement exister.
         */
        if ((int) $equipe->organization_account_id !== (int) $mission->provider_organization_id) {
            return false;
        }

        $membresActifs = FieldTeamMember::query()
            ->where('field_team_id', $equipe->id)
            ->where('is_active', true)
            ->whereNull('left_at')
            ->orderBy('id')
            ->get();

        if ($membresActifs->isEmpty()) {
            return false;
        }

        /*
         * Le chef déclaré s'il fait partie des membres actifs, sinon le premier d'entre eux.
         *
         * `team_lead_user_id` peut désigner quelqu'un qui a quitté l'équipe sans que la colonne ait
         * été mise à jour : le retenir aveuglément assignerait la mission à un absent.
         */
        $responsableId = $membresActifs->contains(
            fn (FieldTeamMember $m) => (int) $m->user_id === (int) $equipe->team_lead_user_id
        )
            ? (int) $equipe->team_lead_user_id
            : (int) $membresActifs->first()->user_id;

        return DB::transaction(function () use (
            $mission, $equipe, $membresActifs, $responsableId, $parUtilisateurId, $motif
        ) {
            $responsable = $this->membreDeLOrganisation($mission, $responsableId);

            // Le chef d'équipe doit être membre ACTIF de la société : une équipe peut retenir
            // quelqu'un que la société a suspendu ou qui l'a quittée.
            if ($responsable === null) {
                return false;
            }

            $this->assigner($mission, $responsable, $parUtilisateurId, $motif);

            $idsRetenus = [$responsableId];

            foreach ($membresActifs as $membre) {
                if ((int) $membre->user_id === $responsableId) {
                    continue;
                }

                $renfort = $this->membreDeLOrganisation($mission, (int) $membre->user_id);

                if ($renfort === null) {
                    continue;
                }

                $this->ajouterRenfort($mission, $renfort);
                $idsRetenus[] = (int) $membre->user_id;
            }

            /*
             * Les renforts de l'équipe PRÉCÉDENTE, libérés en une requête.
             *
             * `assigner()` ne libère que les RESPONSABLES des autres — délibérément, sinon remplacer
             * le chef la veille désassignerait toute l'équipe. Les renforts sont donc à notre charge,
             * et seulement ceux que la nouvelle équipe ne reprend pas.
             */
            MissionAssignment::query()
                ->where('mission_id', $mission->id)
                ->where('assignment_status', 'assigned')
                ->where('role_on_mission', 'helper')
                ->whereNotIn('user_id', $idsRetenus)
                ->update([
                    'assignment_status' => 'released',
                    'reassigned_by' => $parUtilisateurId,
                    'reassignment_reason' => $motif,
                ]);

            $mission->update(['field_team_id' => $equipe->id]);

            return true;
        });
    }

    /**
     * Le membre ACTIF de la société qui exécute cette mission, ou `null`.
     *
     * Appartenir à une équipe terrain et appartenir à la société sont deux choses : une équipe garde
     * ses lignes quand un salarié est suspendu ou s'en va. Assigner sur la seule appartenance à
     * l'équipe confierait la mission à quelqu'un qui n'a plus accès à l'application.
     */
    private function membreDeLOrganisation(Mission $mission, int $userId): ?OrganizationMember
    {
        return OrganizationMember::query()
            ->where('organization_account_id', $mission->provider_organization_id)
            ->where('user_id', $userId)
            ->where('status', 'active')
            ->first();
    }
}

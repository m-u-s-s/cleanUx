<?php

namespace App\Services\Missions;

use App\Models\FieldTeam;
use App\Models\FieldTeamMember;
use App\Models\Mission;
use App\Models\MissionAssignment;
use App\Models\OrganizationMember;
use App\Services\FaceCheck\Exceptions\FaceCheckRequiredException;
use App\Services\FaceCheck\FaceCheckGate;
use App\Services\Organizations\OrganizationNotifier;
use Illuminate\Support\Facades\DB;

/** ASSIGNER UNE MISSION À UN TRAVAILLEUR — UNE SEULE FOIS, POUR TOUTES LES SURFACES. */
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
        // LE SEUL CHEMIN QUI ÉCHAPPAIT À TOUTE GARDE.
        $intervenant = $travailleur->user;

        if ($intervenant !== null && $mission->booking !== null) {
            $verdict = app(FaceCheckGate::class)->inspectForBooking($intervenant, $mission->booking);

            if (! $verdict->allowed()) {
                throw new FaceCheckRequiredException($verdict);
            }
        }

        // Qui perd la mission — relevé AVANT la libération, sinon il n'y a plus rien à lire.
        $sortants = $this->responsablesActifsAutresQue($mission, (int) $travailleur->user_id);

        DB::transaction(function () use ($mission, $travailleur, $parUtilisateurId, $motif) {
            // On libère les RESPONSABLES actifs des autres personnes avant d'installer le nouveau.
            MissionAssignment::query()
                ->where('mission_id', $mission->id)
                ->where('user_id', '!=', $travailleur->user_id)
                ->where('assignment_status', 'assigned')
                ->where(fn ($q) => $q
                    ->where('role_on_mission', '!=', 'helper')
                    ->orWhereNull('role_on_mission')
                )
                // ON TRACE QUI REMPLACE, ET POURQUOI.
                ->update([
                    'assignment_status' => 'reassigned',
                    'reassigned_by' => $parUtilisateurId,
                    'reassignment_reason' => $motif,
                ]);

            MissionAssignment::updateOrCreate(
                ['mission_id' => $mission->id, 'user_id' => $travailleur->user_id],
                ['role_on_mission' => 'lead', 'assignment_status' => 'assigned', 'assigned_at' => now()]
            );

            // LES DEUX COLONNES DU RESPONSABLE, ENSEMBLE.
            $mission->update([
                'status' => 'assigned',
                'lead_provider_user_id' => $travailleur->user_id,
                'lead_employee_id' => $travailleur->user_id,
            ]);

            $this->reporterSurLaReservation($mission, (int) $travailleur->user_id);
        });

        // PERSONNE N'ÉTAIT PRÉVENU — ni l'entrant, ni le sortant.
        $this->prevenirDeLAssignation($mission, (int) $travailleur->user_id, $sortants, $motif);
    }

    /** LA RÉSERVATION SUIT LA MISSION — sinon rien ne les remet jamais d'accord. */
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

        // UNE NOTIFICATION PAR PERSONNE, MÊME SUR UNE ASSIGNATION D'ÉQUIPE.
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

    /** Retirer un renfort. */
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
     * @param  Mission  $mission  déjà résolu et scopé sur l'organisation par l'appelant
     * @return bool `false` si rien n'a pu être fait — équipe d'une autre société, ou sans personne
     */
    public function assignerEquipe(
        Mission $mission,
        FieldTeam $equipe,
        ?int $parUtilisateurId = null,
        ?string $motif = null,
    ): bool {
        // L'ANTI-FUITE ENTRE SOCIÉTÉS, ET ELLE EST ICI PLUTÔT QU'EN CLÉ ÉTRANGÈRE.
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

        // Le chef déclaré s'il fait partie des membres actifs, sinon le premier d'entre eux.
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

            // Les renforts de l'équipe PRÉCÉDENTE, libérés en une requête.
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

    /** Le membre ACTIF de la société qui exécute cette mission, ou `null`. */
    private function membreDeLOrganisation(Mission $mission, int $userId): ?OrganizationMember
    {
        return OrganizationMember::query()
            ->where('organization_account_id', $mission->provider_organization_id)
            ->where('user_id', $userId)
            ->where('status', 'active')
            ->first();
    }
}

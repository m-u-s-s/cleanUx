<?php

namespace App\Services\Organizations;

use App\Enums\OrganizationRole;
use App\Models\Booking;
use App\Models\Channel;
use App\Models\Mission;
use App\Models\MissionAssignment;
use App\Models\OrganizationMember;
use App\Models\User;
use App\Services\PermissionService;
use App\Support\Domain\MissionStatus;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

/** CHANGER LE RÔLE OU LE STATUT D'UN MEMBRE — LES RÈGLES, EN UN SEUL EXEMPLAIRE. */
class OrganizationMemberAdministration
{
    public function __construct(private PermissionService $permissions) {}

    /** Changer le rôle d'un membre. LA COMPARAISON DE RANG EST `<`, PAS `<=`, ET C'EST DÉLIBÉRÉ. */
    public function changerLeRole(
        User $acteur,
        int $organisationId,
        int $membreId,
        string $nouveauRole,
    ): ResultatAdministration {
        $cible = $this->cibleDans($organisationId, $membreId);

        if ($cible === null) {
            return ResultatAdministration::refuse(MotifDeRefus::INTROUVABLE);
        }

        if (! $this->permissions->can($acteur, 'members.edit_role', $organisationId)) {
            return ResultatAdministration::refuse(MotifDeRefus::PERMISSION);
        }

        // `from()` lève un `ValueError` sur une valeur inconnue — un 500 sur une saisie utilisateur.
        $nouveauRoleEnum = OrganizationRole::tryFrom($nouveauRole);

        if ($nouveauRoleEnum === null) {
            return ResultatAdministration::refuse(MotifDeRefus::ROLE_INCONNU);
        }

        $membreActeur = $this->membreDe($acteur, $organisationId);
        $estAdminPlateforme = $acteur->isPlatformAdmin();

        if ($membreActeur !== null && ! $estAdminPlateforme) {
            if ($membreActeur->role->rank() < $cible->role->rank()) {
                return ResultatAdministration::refuse(MotifDeRefus::HIERARCHIE);
            }

            if ($nouveauRoleEnum->rank() >= $membreActeur->role->rank()) {
                return ResultatAdministration::refuse(MotifDeRefus::PROMOTION_TROP_HAUTE);
            }
        }

        if ($nouveauRoleEnum !== OrganizationRole::OWNER && $this->estLeDernierProprietaire($cible)) {
            return ResultatAdministration::refuse(MotifDeRefus::DERNIER_PROPRIETAIRE);
        }

        $cible->update(['role' => $nouveauRoleEnum->value]);
        $this->permissions->invalidateCache($cible->user_id, $organisationId);

        return ResultatAdministration::applique($cible->fresh());
    }

    /** Suspendre, réactiver ou retirer un membre. */
    public function changerLeStatut(
        User $acteur,
        int $organisationId,
        int $membreId,
        string $statut,
        string $permission,
    ): ResultatAdministration {
        $cible = $this->cibleDans($organisationId, $membreId);

        if ($cible === null) {
            return ResultatAdministration::refuse(MotifDeRefus::INTROUVABLE);
        }

        if (! $this->permissions->can($acteur, $permission, $organisationId)) {
            return ResultatAdministration::refuse(MotifDeRefus::PERMISSION);
        }

        $membreActeur = $this->membreDe($acteur, $organisationId);

        if ($membreActeur === null) {
            return ResultatAdministration::refuse(MotifDeRefus::PERMISSION);
        }

        if ($cible->user_id === $acteur->id) {
            return ResultatAdministration::refuse(MotifDeRefus::SOI_MEME);
        }

        if (! $this->permissions->canManageMember($membreActeur, $cible)) {
            return ResultatAdministration::refuse(MotifDeRefus::HIERARCHIE);
        }

        if ($statut !== 'active' && $this->estLeDernierProprietaire($cible)) {
            return ResultatAdministration::refuse(MotifDeRefus::DERNIER_PROPRIETAIRE);
        }

        $cible->update(['status' => $statut]);
        $this->permissions->invalidateCache($cible->user_id, $organisationId);

        // UN DÉPART NE SE CONTENTE PAS DE CHANGER UN STATUT.
        if ($statut === 'left') {
            $this->libererLAvenir($cible->user_id, $organisationId);
        }

        return ResultatAdministration::applique($cible->fresh());
    }

    /** Retirer ce membre laisserait-il l'organisation sans propriétaire actif ? */
    public function estLeDernierProprietaire(OrganizationMember $membre): bool
    {
        if ($membre->role !== OrganizationRole::OWNER || $membre->status !== 'active') {
            return false;
        }

        return OrganizationMember::query()
            ->where('organization_account_id', $membre->organization_account_id)
            ->where('role', 'owner')
            ->where('status', 'active')
            ->where('id', '!=', $membre->id)
            ->doesntExist();
    }

    /**
     * LA RÉSERVATION AUSSI DOIT OUBLIER LA PERSONNE PARTIE.
     *
     * @param  list<int>  $missionIds
     */
    private function libererLesReservations(array $missionIds, int $userId): void
    {
        if ($missionIds === []) {
            return;
        }

        $reservations = Booking::query()
            ->whereIn('id', Mission::query()->whereIn('id', $missionIds)->pluck('booking_id')->filter())
            ->where('employe_id', $userId)
            ->get();

        foreach ($reservations as $reservation) {
            if ($reservation->payment_status === 'authorized' && filled($reservation->stripe_payment_intent_id)) {
                Log::warning('Réservation laissée au nom d’un partant : une retenue bancaire la bloque', [
                    'booking_id' => $reservation->id,
                    'user_id' => $userId,
                    'assigned_provider_organization_id' => $reservation->assigned_provider_organization_id,
                ]);

                continue;
            }

            $reservation->forceFill([
                'employe_id' => null,
                'assigned_provider_user_id' => null,
            ])->save();
        }
    }

    /** Défaire ce qui n'a pas encore eu lieu, et rien d'autre. */
    public function libererLAvenir(int $userId, int $organisationId): void
    {
        $missionsAVenir = Mission::query()
            ->where('provider_organization_id', $organisationId)
            ->where('planned_start_at', '>', now())
            ->pluck('id');

        if ($missionsAVenir->isNotEmpty()) {
            MissionAssignment::query()
                ->whereIn('mission_id', $missionsAVenir)
                ->where('user_id', $userId)
                ->where('assignment_status', 'assigned')
                ->update(['assignment_status' => 'released']);

            // `lead_provider_user_id` est lu par le tableau de bord, l'autorisation Reverb
            // `mission.{id}` et le suivi de trajet : le laisser en place ferait viser les trois sur
            // quelqu'un qui ne viendra pas.
            // `planned` ET NON `pending` : le domaine n'a pas de statut `pending` pour une mission.
            Mission::query()
                ->whereIn('id', $missionsAVenir)
                ->where('lead_provider_user_id', $userId)
                ->update(['lead_provider_user_id' => null, 'status' => MissionStatus::PLANNED]);

            // `lead_employee_id` nomme la MÊME personne et n'était pas libérée : côté web, la
            // mission restait au nom de quelqu'un qui a quitté la société — et `MissionPolicy` lui
            // en laissait l'accès.
            Mission::query()
                ->whereIn('id', $missionsAVenir)
                ->where('lead_employee_id', $userId)
                ->update(['lead_employee_id' => null]);

            $this->libererLesReservations($missionsAVenir->all(), $userId);
        }

        $canaux = Channel::query()
            ->where('organization_account_id', $organisationId)
            ->pluck('id');

        if ($canaux->isNotEmpty()) {
            DB::table('channel_members')
                ->whereIn('channel_id', $canaux)
                ->where('user_id', $userId)
                ->delete();
        }
    }

    /** Le membre visé, cherché DANS l'organisation — jamais chargé puis vérifié. */
    private function cibleDans(int $organisationId, int $membreId): ?OrganizationMember
    {
        return OrganizationMember::query()
            ->where('organization_account_id', $organisationId)
            ->whereKey($membreId)
            ->first();
    }

    private function membreDe(User $acteur, int $organisationId): ?OrganizationMember
    {
        return OrganizationMember::query()
            ->where('organization_account_id', $organisationId)
            ->where('user_id', $acteur->id)
            ->first();
    }
}

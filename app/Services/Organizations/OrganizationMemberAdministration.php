<?php

namespace App\Services\Organizations;

use App\Enums\OrganizationRole;
use App\Models\Channel;
use App\Models\Mission;
use App\Models\MissionAssignment;
use App\Models\OrganizationMember;
use App\Models\User;
use App\Services\PermissionService;
use Illuminate\Support\Facades\DB;

/**
 * CHANGER LE RÔLE OU LE STATUT D'UN MEMBRE — LES RÈGLES, EN UN SEUL EXEMPLAIRE.
 *
 * Ces décisions vivaient dans `TeamManagement`, l'écran web. L'application mobile devant les
 * proposer aussi (« l'owner change les sous-rôles de ses employés quand il veut », y compris depuis
 * le téléphone), les réécrire côté API aurait produit deux jeux de garde-fous — et l'histoire de ce
 * fichier montre à quoi ressemble une divergence : l'écran client et l'écran prestataire, partis du
 * même besoin, avaient chacun une protection que l'autre n'avait pas.
 *
 * SIX RÈGLES, ET AUCUNE N'EST DÉCORATIVE :
 *
 *   1. ISOLATION — la cible est cherchée DANS l'organisation, jamais rattachée après coup.
 *   2. PERMISSION — la clé qui gouverne l'action.
 *   3. HIÉRARCHIE — on n'agit pas sur plus haut ou aussi haut que soi. Sans elle, un directeur
 *      d'opérations à qui l'on accorde `members.edit_role` déclasse le propriétaire.
 *   4. PLAFOND DE PROMOTION — on ne nomme personne à son rang ou au-dessus, sans quoi distribuer un
 *      rôle deviendrait un moyen de se donner un supérieur complaisant.
 *   5. DERNIER PROPRIÉTAIRE — une société sans propriétaire actif n'a plus personne pour inviter,
 *      facturer ou céder ses droits, et aucun écran ne permet d'en nommer un depuis l'extérieur.
 *   6. AUTO-ACTION — on ne se suspend ni ne se retire soi-même.
 *
 * LE SERVICE NE LÈVE PAS D'EXCEPTION HTTP : il rend un `ResultatAdministration`. Voir
 * `MotifDeRefus` — c'est ce qui permet à l'écran web de continuer à refuser en silence là où il le
 * faisait, pendant que l'API répond 403 ou 422.
 */
class OrganizationMemberAdministration
{
    public function __construct(private PermissionService $permissions) {}

    /**
     * Changer le rôle d'un membre.
     *
     * LA COMPARAISON DE RANG EST `<`, PAS `<=`, ET C'EST DÉLIBÉRÉ. `canManageMember()` exige un rang
     * STRICTEMENT supérieur ; l'employer ici interdirait à un propriétaire de déclasser son
     * co-propriétaire — comportement que ce dépôt autorise sciemment : la protection porte sur le
     * DERNIER propriétaire, pas sur le rôle.
     */
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

        /*
         * `from()` lève un `ValueError` sur une valeur inconnue — un 500 sur une saisie
         * utilisateur. C'est le défaut que le lot 1 a fermé sur l'invitation ; il n'a pas à
         * revenir par l'API.
         */
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

    /**
     * Suspendre, réactiver ou retirer un membre.
     *
     * `$permission` est passée par l'appelant parce qu'elle diffère selon le geste :
     * `members.suspend` pour une suspension ou une réactivation, `members.remove` pour un départ.
     */
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

        /*
         * UN DÉPART NE SE CONTENTE PAS DE CHANGER UN STATUT. Les missions de la semaine suivante
         * resteraient assignées à quelqu'un qui ne viendra pas — le répartiteur les verrait
         * « couvertes » et le client découvrirait l'absence le jour même —, et la personne resterait
         * dans les canaux d'équipe, dont l'autorisation Reverb vérifie précisément l'appartenance.
         *
         * Ce nettoyage vit ICI, et non chez l'appelant : l'API mobile l'aurait oublié, et son oubli
         * ne se serait vu qu'une semaine plus tard, sur le terrain.
         */
        if ($statut === 'left') {
            $this->libererLAvenir($cible->user_id, $organisationId);
        }

        return ResultatAdministration::applique($cible->fresh());
    }

    /**
     * Retirer ce membre laisserait-il l'organisation sans propriétaire actif ?
     *
     * Vaut pour la suppression, la suspension et le déclassement — trois façons de perdre le
     * dernier propriétaire.
     *
     * `$membre->role` est CASTÉ EN ENUM par le modèle : le comparer à la chaîne `'owner'` serait
     * toujours faux et la garde ne s'armerait jamais. C'est le défaut qui rendait
     * `PermissionService::canManageMember()` inopérante avant le 2026-08-05.
     */
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
     * Défaire ce qui n'a pas encore eu lieu, et rien d'autre.
     *
     * Les missions PASSÉES gardent leur intervenant : c'est l'historique de la société, et la
     * facturation comme les réclamations s'y appuient. Les MESSAGES restent aussi — un fil dont la
     * moitié disparaît devient illisible pour ceux qui restent.
     */
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
            Mission::query()
                ->whereIn('id', $missionsAVenir)
                ->where('lead_provider_user_id', $userId)
                ->update(['lead_provider_user_id' => null, 'status' => 'pending']);
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

    /**
     * Le membre visé, cherché DANS l'organisation — jamais chargé puis vérifié.
     *
     * Un identifiant appartenant à une autre société n'est donc jamais lu, et la différence entre
     * un 403 et un 404 n'apprend rien sur ce qui existe ailleurs.
     */
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

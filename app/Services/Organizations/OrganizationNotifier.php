<?php

namespace App\Services\Organizations;

use App\Models\OrganizationMember;
use App\Models\PushNotification;
use App\Models\User;
use App\Services\PermissionService;
use App\Services\Push\PushService;
use Illuminate\Support\Facades\Log;

/**
 * PRÉVENIR LES GENS DE CE QU'ON DÉCIDE POUR EUX.
 *
 * `MissionAssignmentService::assigner()` n'envoyait AUCUNE notification. Ni l'entrant ni le sortant
 * n'étaient prévenus : quelqu'un se voyait retirer la mission de demain sans le savoir, et
 * l'apprenait en arrivant sur place — ou n'y allait pas. Le remplaçant, lui, découvrait sa mission
 * en ouvrant l'application, s'il l'ouvrait.
 *
 * DEUX DESTINATAIRES POSSIBLES, ET C'EST TOUT CE QUE FAIT CETTE CLASSE : une PERSONNE, ou tous les
 * porteurs d'une PERMISSION dans une organisation. La seconde primitive n'existait nulle part —
 * « prévenir les dispatcheurs » supposait de recopier la résolution de rôles, qui aurait ignoré la
 * matrice réglable de la société.
 *
 * SOFT-FAIL, TOUJOURS. Une notification qui échoue ne doit jamais faire échouer l'assignation
 * elle-même : le travail est distribué, c'est le fait principal. L'échec est journalisé, pas
 * propagé — sinon un jeton d'appareil périmé empêcherait de répartir les missions du jour.
 */
class OrganizationNotifier
{
    public function __construct(
        private PushService $push,
        private PermissionService $permissions,
    ) {}

    /**
     * Prévenir une personne.
     *
     * @param  array<string, mixed>  $donnees  charge utile lue par l'application pour ouvrir le bon écran
     */
    public function notifierUtilisateur(
        ?int $userId,
        string $titre,
        string $corps,
        array $donnees = [],
        ?string $cleIdempotence = null,
    ): void {
        if ($userId === null) {
            return;
        }

        $utilisateur = User::find($userId);

        if ($utilisateur === null) {
            return;
        }

        try {
            $this->push->dispatchToUser(
                user: $utilisateur,
                title: $titre,
                body: $corps,
                data: $donnees,
                // TRANSACTIONNEL, pas marketing : on ne demande pas l'avis de quelqu'un avant de
                // lui dire que son planning de demain a changé. La catégorie gouverne aussi
                // l'opt-in, et une mission assignée n'est pas une offre commerciale.
                category: PushNotification::CATEGORY_TRANSACTIONAL,
                idempotencyKey: $cleIdempotence,
            );
        } catch (\Throwable $e) {
            Log::warning('Notification société non délivrée', [
                'user_id' => $userId,
                'raison' => $e->getMessage(),
            ]);
        }
    }

    /**
     * Prévenir tous les porteurs d'une permission dans une organisation.
     *
     * « Alerter les dispatcheurs » ne se résout pas par une liste de rôles : une société peut avoir
     * réglé sa propre matrice, et une liste recopiée ici l'ignorerait. On demande donc à
     * `PermissionService`, membre par membre — le cache de 60 s rend le coût négligeable, et la
     * réponse suit les réglages de la maison.
     *
     * @param  array<string, mixed>  $donnees
     * @param  ?int  $saufUtilisateurId  ne pas se notifier soi-même quand on est l'auteur du geste
     */
    public function notifierPorteursDe(
        int $organisationId,
        string $permission,
        string $titre,
        string $corps,
        array $donnees = [],
        ?int $saufUtilisateurId = null,
        ?string $cleIdempotence = null,
    ): int {
        $membres = OrganizationMember::query()
            ->where('organization_account_id', $organisationId)
            ->where('status', 'active')
            ->with('user')
            ->get();

        $prevenus = 0;

        foreach ($membres as $membre) {
            if ($saufUtilisateurId !== null && (int) $membre->user_id === $saufUtilisateurId) {
                continue;
            }

            $utilisateur = $membre->user;

            if ($utilisateur === null
                || ! $this->permissions->can($utilisateur, $permission, $organisationId)) {
                continue;
            }

            $this->notifierUtilisateur(
                userId: (int) $membre->user_id,
                titre: $titre,
                corps: $corps,
                donnees: $donnees,
                cleIdempotence: $cleIdempotence !== null ? "{$cleIdempotence}:u{$membre->user_id}" : null,
            );

            $prevenus++;
        }

        return $prevenus;
    }
}

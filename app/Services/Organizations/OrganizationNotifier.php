<?php

namespace App\Services\Organizations;

use App\Models\OrganizationMember;
use App\Models\PushNotification;
use App\Models\User;
use App\Services\PermissionService;
use App\Services\Push\PushService;
use Illuminate\Support\Facades\Log;

/** PRÉVENIR LES GENS DE CE QU'ON DÉCIDE POUR EUX. */
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

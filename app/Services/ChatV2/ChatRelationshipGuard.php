<?php

namespace App\Services\ChatV2;

use App\Models\Booking;
use App\Models\ChatParticipant;
use App\Models\ChatThread;
use App\Models\ComplaintCase;
use App\Models\CustomerClaim;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Validation\ValidationException;

/** QUI A LE DROIT D'OUVRIR UN FIL AVEC QUI. `createThread` ne posait AUCUNE question. */
class ChatRelationshipGuard
{
    /**
     * @param  array<int, array{user_id?: int|string, role?: string}>  $participants
     *
     * @throws ValidationException
     */
    public function assertPeutOuvrirUnFil(
        User $auteur,
        array $participants,
        ?string $contextType,
        ?int $contextId,
    ): void {
        $autres = $this->autresParticipants($auteur, $participants);

        if ($auteur->isAdmin()) {
            return;
        }

        $this->assertRolesPermis($auteur, $participants);

        if ($autres === []) {
            $this->assertPeutRejoindreSeul($auteur, $contextType, $contextId);

            return;
        }

        foreach ($autres as $userId) {
            if (! $this->partagentUneRelation($auteur->id, $userId, $contextType, $contextId)) {
                throw ValidationException::withMessages([
                    'participants' => [
                        'Vous ne pouvez ouvrir une conversation qu’avec une personne liée à une de vos '.
                        'interventions ou à un de vos litiges.',
                    ],
                ]);
            }
        }
    }

    /**
     * Le rôle `admin` dans un fil ouvre la modération de ce fil.
     *
     * @param  array<int, array{user_id?: int|string, role?: string}>  $participants
     *
     * @throws ValidationException
     */
    protected function assertRolesPermis(User $auteur, array $participants): void
    {
        foreach ($participants as $p) {
            $role = (string) ($p['role'] ?? ChatParticipant::ROLE_CLIENT);

            if (! in_array($role, [ChatParticipant::ROLE_ADMIN, ChatParticipant::ROLE_SYSTEM], true)) {
                continue;
            }

            throw ValidationException::withMessages([
                'participants' => ['Seule l’administration peut attribuer ce rôle dans un fil.'],
            ]);
        }
    }

    /**
     * SE DÉCLARER SEUL PARTICIPANT NE DOIT PAS OUVRIR LE FIL D'AUTRUI. `startThread` RÉUTILISE le
     * fil actif d'un contexte : s'y nommer seul y donnait accès en lecture et en écriture.
     *
     * Ouvrir un fil NEUF où l'on est seul ne touche personne et reste permis ; c'est rejoindre un
     * fil existant sur un contexte qu'on ne partage pas qui est le contournement.
     *
     * @throws ValidationException
     */
    protected function assertPeutRejoindreSeul(User $auteur, ?string $contextType, ?int $contextId): void
    {
        if (! $contextType || ! $contextId) {
            return;
        }

        $filExistant = ChatThread::query()
            ->forContext($contextType, $contextId)
            ->where('status', ChatThread::STATUS_ACTIVE)
            ->exists();

        if (! $filExistant || $this->estPartiePrenante((int) $auteur->id, $contextType, $contextId)) {
            return;
        }

        throw ValidationException::withMessages([
            'context_id' => [
                'Vous ne pouvez pas rejoindre une conversation rattachée à une intervention ou à un '.
                'litige qui ne vous concerne pas.',
            ],
        ]);
    }

    /** Une seule personne, un seul contexte : « cette personne est-elle partie à ce dossier ? ». */
    protected function estPartiePrenante(int $userId, string $contextType, int $contextId): bool
    {
        if ($contextType === 'booking') {
            return Booking::query()
                ->whereKey($contextId)
                ->where(fn ($q) => $this->filtreRoles($q, $userId))
                ->exists();
        }

        if ($contextType === 'dispute') {
            $reclamation = CustomerClaim::query()->find($contextId);

            if ($reclamation) {
                return $this->nommeOuLieALaReservation(
                    $userId,
                    [$reclamation->customer_user_id, $reclamation->assigned_to],
                    $reclamation->booking_id,
                );
            }

            $dossier = ComplaintCase::query()->find($contextId);

            return $dossier !== null && $this->nommeOuLieALaReservation(
                $userId,
                [$dossier->client_id, $dossier->provider_user_id, $dossier->assigned_to],
                $dossier->booking_id,
            );
        }

        // `admin` et `generic` ne nomment aucune partie ; seul l'administrateur, sorti plus haut, y entre.
        return false;
    }

    /** @param  array<int, int|string|null>  $nommes */
    protected function nommeOuLieALaReservation(int $userId, array $nommes, mixed $bookingId): bool
    {
        $impliques = array_filter(array_map('intval', $nommes));

        if (in_array($userId, $impliques, true)) {
            return true;
        }

        return $bookingId ? $this->estPartiePrenante($userId, 'booking', (int) $bookingId) : false;
    }

    /**
     * @param  array<int, array{user_id?: int|string, role?: string}>  $participants
     * @return array<int, int>
     */
    protected function autresParticipants(User $auteur, array $participants): array
    {
        $ids = [];

        foreach ($participants as $p) {
            $userId = (int) ($p['user_id'] ?? 0);

            if ($userId > 0 && $userId !== (int) $auteur->id) {
                $ids[$userId] = $userId;
            }
        }

        return array_values($ids);
    }

    /** Une relation existe quand les deux personnes figurent sur la MÊME réservation, dans n'importe lequel des quatre rôles qu'une réservation distingue (le client de compte, le client payeur, l'employé affecté, le prestataire assigné). */
    protected function partagentUneRelation(
        int $auteurId,
        int $autreId,
        ?string $contextType,
        ?int $contextId,
    ): bool {
        if ($contextType === 'dispute' && $contextId) {
            if ($this->partagentUnLitige($auteurId, $autreId, $contextId)) {
                return true;
            }
        }

        $requete = Booking::query();

        if ($contextType === 'booking' && $contextId) {
            $requete->whereKey($contextId);
        }

        return $requete
            ->where(fn ($q) => $this->filtreRoles($q, $auteurId))
            ->where(fn ($q) => $this->filtreRoles($q, $autreId))
            ->exists();
    }

    /**
     * Le constructeur est bien celui d'Eloquent, et pas celui de la requête brute : une fermeture passée à `where()` sur un modèle en reçoit un nouveau.
     *
     * @param  Builder<Booking>  $q
     */
    protected function filtreRoles($q, int $userId): void
    {
        // L'INTERVENANT OUVRE LE DROIT DE PARLER AU CLIENT, et il vit sur la mission.
        $q->where('client_id', $userId)
            ->orWhere('customer_user_id', $userId)
            ->orWhere('employe_id', $userId)
            ->orWhere('assigned_provider_user_id', $userId)
            ->orWhereHas('missions', fn ($m) => $m->where('lead_provider_user_id', $userId));
    }

    /**
     * LES DEUX DOIVENT ÊTRE PARTIES, PAS UN SEUL. Le `||` d'avant accordait le fil dès que L'AUTRE
     * était le plaignant : l'auteur, lui, n'avait besoin d'aucun lien avec le dossier.
     */
    protected function partagentUnLitige(int $auteurId, int $autreId, int $litigeId): bool
    {
        return $this->estPartiePrenante($auteurId, 'dispute', $litigeId)
            && $this->estPartiePrenante($autreId, 'dispute', $litigeId);
    }
}

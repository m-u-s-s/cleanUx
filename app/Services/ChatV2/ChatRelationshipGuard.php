<?php

namespace App\Services\ChatV2;

use App\Models\Booking;
use App\Models\ChatParticipant;
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

    protected function partagentUnLitige(int $auteurId, int $autreId, int $litigeId): bool
    {
        $reclamation = CustomerClaim::query()->find($litigeId);

        if ($reclamation) {
            $impliques = array_filter([
                (int) $reclamation->customer_user_id,
                (int) $reclamation->assigned_to,
            ]);

            if (in_array($auteurId, $impliques, true) && in_array($autreId, $impliques, true)) {
                return true;
            }

            if ($reclamation->booking_id) {
                return $this->partagentUneRelation($auteurId, $autreId, 'booking', (int) $reclamation->booking_id);
            }

            return false;
        }

        $dossier = ComplaintCase::query()->find($litigeId);

        // Un dossier de réclamation ne nomme que son client : le lien passe alors par la
        // réservation, comme pour tout le reste.
        return $dossier !== null
            && ((int) $dossier->client_id === $auteurId || (int) $dossier->client_id === $autreId);
    }
}

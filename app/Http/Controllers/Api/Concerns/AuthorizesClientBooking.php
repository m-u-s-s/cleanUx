<?php

namespace App\Http\Controllers\Api\Concerns;

use App\Models\Booking;
use App\Models\User;

/**
 * « Cette réservation est-elle la vôtre ? » — une seule réponse, pour tous les points d'entrée
 * clients.
 *
 * La règle vivait dans `ClientBookingController`, en `protected`, donc inatteignable pour le
 * contrôleur suivant qui en aurait besoin — lequel l'aurait réécrite, et l'aurait réécrite un peu
 * différemment. Trois conditions y tiennent, et chacune couvre un cas que les deux autres
 * manquent : le particulier qui a commandé, le collègue d'une société cliente, l'administrateur.
 *
 * `customer_user_id` ET `client_id` : les deux colonnes désignent le commanditaire selon la porte
 * d'entrée employée. N'en lire qu'une refuse l'accès au propriétaire une fois sur deux.
 */
trait AuthorizesClientBooking
{
    protected function clientPeutVoirLaReservation(?User $user, Booking $booking): bool
    {
        if (! $user instanceof User) {
            return false;
        }

        $organisationId = $user->organization_account_id ?? $user->current_organization_id ?? null;

        $estCommanditaire = (int) ($booking->customer_user_id ?? 0) === (int) $user->id
            || (int) ($booking->client_id ?? 0) === (int) $user->id;

        $estMembreDeLOrganisation = $organisationId
            && $booking->customer_organization_id
            && (int) $booking->customer_organization_id === (int) $organisationId;

        return $estCommanditaire || (bool) $estMembreDeLOrganisation || $user->isPlatformAdmin();
    }

    protected function assertClientPeutVoirLaReservation(?User $user, Booking $booking): void
    {
        abort_if(! $this->clientPeutVoirLaReservation($user, $booking), 403, 'Accès refusé.');
    }
}

<?php

namespace App\Http\Controllers\Api\Concerns;

use App\Models\Booking;
use App\Models\User;

/** « Cette réservation est-elle la vôtre ? */
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

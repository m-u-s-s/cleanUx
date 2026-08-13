<?php

namespace App\Policies;

use App\Models\Booking;
use App\Models\User;

class RendezVousPolicy
{
    public function create(User $user): bool
    {
        return $user->isClient()
            || $user->isEntreprise()
            || ($user->isAdmin() && ! $user->isReadOnlyAdmin());
    }

    public function view(User $user, Booking $rendezVous): bool
    {
        if ($user->isAdmin()) {
            return true;
        }

        if ($user->isClient()) {
            return $rendezVous->client_id === $user->id;
        }

        if ($user->isEmploye()) {
            // C'est L'INTERVENANT qui a le droit de regard, pas le nom resté sur la commande :
            // après une réassignation, l'ancien conservait l'accès et le nouveau se le voyait
            // refuser. Voir `Booking::intervenantId()`.
            return $rendezVous->intervenantId() === $user->id;
        }

        return false;
    }

    public function update(User $user, Booking $rendezVous): bool
    {
        if ($user->isAdmin() && ! $user->isReadOnlyAdmin()) {
            return true;
        }

        if ($user->isClient()) {
            return $rendezVous->client_id === $user->id
                && in_array($rendezVous->status, [
                    'en_attente',
                    'confirme',
                ], true);
        }

        return false;
    }

    public function reschedule(User $user, Booking $rendezVous): bool
    {
        return $this->update($user, $rendezVous);
    }

    public function cancel(User $user, Booking $rendezVous): bool
    {
        if ($user->isAdmin() && ! $user->isReadOnlyAdmin()) {
            return true;
        }

        return $user->isClient()
            && $rendezVous->client_id === $user->id
            && in_array($rendezVous->status, [
                'en_attente',
                'confirme',
            ], true);
    }

    public function assignEmployee(User $user, Booking $rendezVous): bool
    {
        return $user->isAdmin()
            && ! $user->isReadOnlyAdmin()
            && $user->hasPermission('manage-calendar');
    }

    public function delete(User $user, Booking $rendezVous): bool
    {
        return $user->isAdmin()
            && $user->canPerformCriticalAdminActions();
    }
}

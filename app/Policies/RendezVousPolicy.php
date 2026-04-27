<?php

namespace App\Policies;

use App\Models\RendezVous;
use App\Models\User;

class RendezVousPolicy
{
    public function view(User $user, RendezVous $rendezVous): bool
    {
        if ($user->isAdmin()) {
            return true;
        }

        if ($user->isClient()) {
            return $rendezVous->client_id === $user->id;
        }

        if ($user->isEmploye()) {
            return $rendezVous->employe_id === $user->id;
        }

        return false;
    }

    public function update(User $user, RendezVous $rendezVous): bool
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

    public function reschedule(User $user, RendezVous $rendezVous): bool
    {
        return $this->update($user, $rendezVous);
    }

    public function cancel(User $user, RendezVous $rendezVous): bool
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

    public function assignEmployee(User $user, RendezVous $rendezVous): bool
    {
        return $user->isAdmin()
            && ! $user->isReadOnlyAdmin()
            && $user->hasPermission('manage-calendar');
    }

    public function delete(User $user, RendezVous $rendezVous): bool
    {
        return $user->isAdmin()
            && $user->canPerformCriticalAdminActions();
    }
}
<?php

namespace App\Policies;

use App\Models\RendezVous;
use App\Models\User;
use App\Support\AdminScope;

class RendezVousPolicy
{
    public function view(User $user, RendezVous $rendezVous): bool
    {
        if ($user->isAdmin()) {
            return AdminScope::canAccessRendezVous($user, $rendezVous);
        }

        if ($user->isClient()) {
            return $rendezVous->client_id === $user->id;
        }

        if ($user->isEmploye()) {
            return $rendezVous->employe_id === $user->id;
        }

        return false;
    }

    public function create(User $user): bool
    {
        return $user->isClient();
    }

    public function update(User $user, RendezVous $rendezVous): bool
    {
        if ($user->isAdmin()) {
            return $user->canPerformCriticalAdminActions()
                && AdminScope::canAccessRendezVous($user, $rendezVous);
        }

        if ($user->isClient()) {
            return $rendezVous->client_id === $user->id
                && $rendezVous->canStillBeEditedByClient();
        }

        if ($user->isEmploye()) {
            return $rendezVous->employe_id === $user->id;
        }

        return false;
    }

    public function delete(User $user, RendezVous $rendezVous): bool
    {
        if ($user->isAdmin()) {
            return $user->canPerformCriticalAdminActions()
                && AdminScope::canAccessRendezVous($user, $rendezVous);
        }

        if ($user->isClient()) {
            return $rendezVous->client_id === $user->id
                && $rendezVous->canStillBeEditedByClient();
        }

        return false;
    }
}

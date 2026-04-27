<?php

namespace App\Policies;

use App\Models\Mission;
use App\Models\User;

class MissionPolicy
{
    public function view(User $user, Mission $mission): bool
    {
        if ($user->isAdmin()) {
            return true;
        }

        if ($user->isEmploye()) {
            return $mission->lead_employee_id === $user->id
                || $mission->assignments()->where('user_id', $user->id)->exists();
        }

        if ($user->isClient()) {
            return $mission->rendezVous?->client_id === $user->id;
        }

        return false;
    }

    public function update(User $user, Mission $mission): bool
    {
        if ($user->isAdmin() && ! $user->isReadOnlyAdmin()) {
            return true;
        }

        if ($user->isEmploye()) {
            return $mission->lead_employee_id === $user->id
                || $mission->assignments()->where('user_id', $user->id)->exists();
        }

        return false;
    }

    public function start(User $user, Mission $mission): bool
    {
        return $user->isEmploye()
            && (
                $mission->lead_employee_id === $user->id
                || $mission->assignments()->where('user_id', $user->id)->exists()
            );
    }

    public function close(User $user, Mission $mission): bool
    {
        return $this->start($user, $mission);
    }

    public function track(User $user, Mission $mission): bool
    {
        return $this->start($user, $mission);
    }

    public function delete(User $user, Mission $mission): bool
    {
        return $user->isAdmin()
            && $user->canPerformCriticalAdminActions();
    }
}
<?php

namespace App\Policies;

use App\Models\Mission;
use App\Models\OrganizationAccount;
use App\Models\OrganizationMember;
use App\Models\User;
use App\Services\PermissionService;

class MissionPolicy
{
    /** LA GARDE SERVEUR DE L'EXIGENCE 8. */
    public function view(User $user, Mission $mission): bool
    {
        if ($user->isAdmin()) {
            return true;
        }

        // Assigné : la voie la plus courte, et la seule qu'un worker emprunte.
        if ($mission->estIntervenant($user)) {
            return true;
        }

        if ($this->piloteLaSocieteDeLaMission($user, $mission)) {
            return true;
        }

        if ($user->isClient()) {
            return $mission->booking?->client_id === $user->id;
        }

        return false;
    }

    /** Membre ACTIF de la société qui exécute la mission, et porteur de `missions.view_all`. */
    private function piloteLaSocieteDeLaMission(User $user, Mission $mission): bool
    {
        $organisationId = $mission->provider_organization_id;

        if ($organisationId === null) {
            return false;
        }

        $organisation = OrganizationAccount::find($organisationId);

        if ($organisation === null) {
            return false;
        }

        $adhesionActive = OrganizationMember::query()
            ->where('organization_account_id', $organisationId)
            ->where('user_id', $user->id)
            ->where('status', 'active')
            ->exists();

        return $adhesionActive
            && app(PermissionService::class)->can($user, 'missions.view_all', $organisation);
    }

    public function update(User $user, Mission $mission): bool
    {
        if ($user->isAdmin() && ! $user->isReadOnlyAdmin()) {
            return true;
        }

        if ($user->isEmploye()) {
            return $mission->estIntervenant($user);
        }

        return false;
    }

    public function start(User $user, Mission $mission): bool
    {
        return $user->isEmploye() && $mission->estIntervenant($user);
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

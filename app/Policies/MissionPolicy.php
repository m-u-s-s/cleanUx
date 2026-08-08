<?php

namespace App\Policies;

use App\Models\Mission;
use App\Models\OrganizationAccount;
use App\Models\OrganizationMember;
use App\Models\User;
use App\Services\PermissionService;

class MissionPolicy
{
    /**
     * LA GARDE SERVEUR DE L'EXIGENCE 8.
     *
     * « Un worker ne peut ni voir ni suivre une mission qui ne lui est pas assignée — ni à l'écran,
     * ni via l'API. » Masquer un bouton ne suffit pas : l'URL reste tapable.
     *
     * DEUX MANQUES CORRIGÉS ICI, symétriques :
     *
     * - L'ORGANISATION ÉTAIT IGNORÉE. Un owner ne pouvait pas ouvrir `/missions/{id}` de sa PROPRE
     *   société : la politique regardait le rôle plateforme (`isEmploye()`) et l'assignation
     *   nominative, jamais l'appartenance. Un gérant qui répartit les missions ne peut pas en
     *   ouvrir une seule — l'écran de détail société n'avait aucun moyen d'exister.
     * - LE RANG NE TRAVERSE PAS LES SOCIÉTÉS. Être owner quelque part n'ouvre rien ailleurs :
     *   l'appartenance ET la permission sont évaluées sur `mission.provider_organization_id`,
     *   jamais sur l'organisation courante de qui regarde.
     */
    public function view(User $user, Mission $mission): bool
    {
        if ($user->isAdmin()) {
            return true;
        }

        // Assigné : la voie la plus courte, et la seule qu'un worker emprunte.
        if ($mission->lead_employee_id === $user->id
            || $mission->assignments()->where('user_id', $user->id)->exists()) {
            return true;
        }

        if ($this->piloteLaSocieteDeLaMission($user, $mission)) {
            return true;
        }

        if ($user->isClient()) {
            return $mission->rendezVous?->client_id === $user->id;
        }

        return false;
    }

    /**
     * Membre ACTIF de la société qui exécute la mission, et porteur de `missions.view_all`.
     *
     * L'appartenance seule ne suffit pas : un worker est membre actif, et c'est précisément à lui
     * que l'exigence 8 ferme la porte.
     */
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

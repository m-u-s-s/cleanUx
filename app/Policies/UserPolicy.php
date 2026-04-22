<?php

namespace App\Policies;

use App\Models\User;
use App\Support\AdminScope;

class UserPolicy
{
    public function manage(User $user): bool
    {
        return $user->canAccessAdminModule('manage-users');
    }

    public function updateRole(User $user, User $target): bool
    {
        return $user->canPerformCriticalAdminActions()
            && $user->canAccessAdminModule('manage-users')
            && AdminScope::canAccessUser($user, $target)
            && $user->id !== $target->id;
    }

    public function toggleActivation(User $user, User $target): bool
    {
        return $user->canPerformCriticalAdminActions()
            && $user->canAccessAdminModule('manage-users')
            && AdminScope::canAccessUser($user, $target)
            && $user->id !== $target->id;
    }

    public function updateAdminSecurity(User $user, User $target): bool
    {
        return $user->canPerformCriticalAdminActions()
            && $user->canAccessAdminModule('manage-users')
            && $target->isAdmin()
            && AdminScope::canAccessUser($user, $target)
            && $user->id !== $target->id;
    }

    public function import(User $user): bool
    {
        return $user->canPerformCriticalAdminActions() && $user->canAccessAdminModule('manage-users');
    }

    public function export(User $user): bool
    {
        return $user->canPerformCriticalAdminActions();
    }
}

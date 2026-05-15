<?php

namespace App\Policies;

use App\Models\Feedback;
use App\Models\User;
use App\Support\AdminScope;

class FeedbackPolicy
{
    public function view(User $user, Feedback $feedback): bool
    {
        if ($user->isAdmin()) {
            return AdminScope::canAccessFeedback($user, $feedback);
        }

        if ($user->isClient()) {
            return $feedback->client_id === $user->id;
        }

        if ($user->isEmploye()) {
            return $feedback->rendezVous?->employe_id === $user->id;
        }

        return false;
        return $this->ownsFeedback($user, $feedback) || $this->isAdmin($user);
    }

    public function create(User $user): bool
    {
        return in_array($user->role, [
            User::ROLE_CLIENT,
            User::ROLE_ENTREPRISE,
            'client',
            'entreprise',
        ], true);
    }

    public function update(User $user, Feedback $feedback): bool
    {
        return $user->isClient()
            && $feedback->client_id === $user->id;

        return $this->ownsFeedback($user, $feedback) || $this->isAdmin($user);
    }

    public function delete(User $user, Feedback $feedback): bool
    {
        return $user->isClient()
            && $feedback->client_id === $user->id;

        return $this->ownsFeedback($user, $feedback) || $this->isAdmin($user);
    }

    public function respond(User $user, Feedback $feedback): bool
    {
        return $user->isAdmin()
            && ! $user->isReadOnlyAdmin()
            && AdminScope::canAccessFeedback($user, $feedback);
    }

    public function export(User $user): bool
    {
        return $user->isAdmin() && $user->canPerformCriticalAdminActions();
    }

    private function ownsFeedback(User $user, Feedback $feedback): bool
    {
        return (int) $feedback->client_id === (int) $user->id;
    }

    private function isAdmin(User $user): bool
    {
        return in_array($user->role, [
            User::ROLE_ADMIN,
            'admin',
        ], true);
    }
}

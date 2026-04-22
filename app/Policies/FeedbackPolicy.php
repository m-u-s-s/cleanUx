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
    }

    public function create(User $user): bool
    {
        return $user->isClient();
    }

    public function update(User $user, Feedback $feedback): bool
    {
        return $user->isClient()
            && $feedback->client_id === $user->id;
    }

    public function delete(User $user, Feedback $feedback): bool
    {
        return $user->isClient()
            && $feedback->client_id === $user->id;
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
}

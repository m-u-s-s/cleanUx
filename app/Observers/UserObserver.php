<?php

namespace App\Observers;

use App\Models\User;

/** CHANGER DE NUMÉRO PERD LA VÉRIFICATION DU NUMÉRO. */
class UserObserver
{
    public function saving(User $user): void
    {
        if (! $user->exists) {
            return;
        }

        if (! $user->isDirty('phone')) {
            return;
        }

        if ($user->isDirty('phone_verified_at')) {
            return;
        }

        $user->phone_verified_at = null;
    }
}

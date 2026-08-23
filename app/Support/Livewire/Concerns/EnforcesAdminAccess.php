<?php

namespace App\Support\Livewire\Concerns;

use Illuminate\Support\Facades\Auth;

/** P2 — defense-in-depth admin guard for admin Livewire components. */
trait EnforcesAdminAccess
{
    public function bootEnforcesAdminAccess(): void
    {
        $user = Auth::user();
        abort_unless($user !== null && $user->isAdmin(), 403);
    }
}

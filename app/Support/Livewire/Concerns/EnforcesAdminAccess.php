<?php

namespace App\Support\Livewire\Concerns;

use Illuminate\Support\Facades\Auth;

/**
 * P2 — defense-in-depth admin guard for admin Livewire components.
 *
 * Every /admin/* route is already gated by the role:admin middleware, but only ~1 of the 95 admin
 * components also guarded in mount(). This trait makes the guarantee component-local and enforced
 * on EVERY request (Livewire boot{Trait} hook → mount + every action), so the protection survives
 * future route refactors and any non-standard mount path. Aborts 403 unless an admin is signed in.
 *
 * Finer-grained checks (readonly-admin write restrictions, zone-scoped data filtering) remain the
 * responsibility of each component/action; this is the coarse "is an admin at all" gate.
 */
trait EnforcesAdminAccess
{
    public function bootEnforcesAdminAccess(): void
    {
        $user = Auth::user();
        abort_unless($user !== null && $user->isAdmin(), 403);
    }
}

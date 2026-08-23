<?php

namespace App\Livewire\SuperAdmin;

use App\Enums\Role;
use App\Models\User;
use Illuminate\Contracts\View\View;
use Livewire\Component;

/** Le cockpit du super administrateur. CE QU'IL MONTRE, ET POURQUOI CE N'EST PAS LA CONSOLE. */
class SuperAdminDashboard extends Component
{
    /**
     * La population de la plateforme, par rôle canonique.
     *
     * @return array<string, int>
     */
    public function comptesParRole(): array
    {
        $comptes = array_fill_keys(array_map(fn (Role $r) => $r->value, Role::cases()), 0);

        User::query()
            ->with(['customerProfile', 'providerProfile'])
            ->chunk(500, function ($utilisateurs) use (&$comptes) {
                foreach ($utilisateurs as $utilisateur) {
                    $comptes[$utilisateur->roleCanonique()->value]++;
                }
            });

        return $comptes;
    }

    public function render(): View
    {
        return view('livewire.super-admin.dashboard', [
            'comptes' => $this->comptesParRole(),
            'total' => User::query()->count(),
        ]);
    }
}

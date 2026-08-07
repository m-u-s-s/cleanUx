<?php

namespace App\Livewire\SuperAdmin;

use App\Enums\Role;
use App\Models\User;
use Illuminate\Contracts\View\View;
use Livewire\Component;

/**
 * Le cockpit du super administrateur.
 *
 * CE QU'IL MONTRE, ET POURQUOI CE N'EST PAS LA CONSOLE. La console d'administration pilote
 * l'exploitation : rendez-vous, missions, zones, litiges. Ce tableau-ci regarde la PLATEFORME
 * elle-même — qui sont ses comptes, comment ils se répartissent entre les six rôles, et où sont
 * les leviers qui engagent tout le monde à la fois (utilisateurs, drapeaux de fonctionnalité,
 * préparation au déploiement).
 *
 * Il ne remplace donc rien : le super administrateur garde accès aux 83 modules d'administration,
 * son contexte de modules étant le même que celui de l'administrateur.
 */
class SuperAdminDashboard extends Component
{
    /**
     * La population de la plateforme, par rôle canonique.
     *
     * LE COMPTAGE SE FAIT EN PHP, PAS EN SQL, ET C'EST ASSUMÉ. Le rôle canonique se résout à
     * partir de cinq signaux répartis sur trois tables — une requête d'agrégat devrait recopier
     * cette logique en SQL, et les deux versions divergeraient au premier changement de règle.
     * Sur une plateforme qui compte ses comptes en milliers, la lecture reste bornée par les
     * relations chargées d'avance ; si elle devient trop lourde, la réponse est une colonne
     * dénormalisée tenue par un observateur, pas un second jeu de règles.
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

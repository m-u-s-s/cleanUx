<?php

namespace App\Livewire\Shared;

use App\Support\Navigation\ModuleCatalogue;
use Illuminate\Contracts\View\View;
use Livewire\Component;

/**
 * Le répertoire des modules d'un tableau de bord.
 *
 * Un seul composant pour les cinq contextes : ce qui change entre eux est une donnée du registre,
 * pas du code.
 *
 * LE CONTEXTE VIENT DE LA ROUTE, JAMAIS D'UNE ENTRÉE UTILISATEUR. Chaque route vit déjà dans le
 * groupe de middleware de son rôle (`role:admin`, `org.type:provider`…), qui reste le seul juge
 * des droits : cette page ne garde rien, elle liste. Un contexte arrivant par la requête
 * permettrait d'afficher la liste des modules d'administration à qui n'y a pas droit — les cases
 * ne s'ouvriraient pas, mais la liste elle-même renseigne sur la plateforme.
 */
class ModulesDirectory extends Component
{
    public string $contexte = 'client';

    public function mount(string $contexte): void
    {
        $this->contexte = $contexte;
    }

    public function render(): View
    {
        return view('livewire.shared.modules-directory', [
            'groupes' => ModuleCatalogue::pourContexte($this->contexte),
        ]);
    }
}

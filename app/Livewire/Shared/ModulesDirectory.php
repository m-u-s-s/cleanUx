<?php

namespace App\Livewire\Shared;

use App\Support\Navigation\ModuleCatalogue;
use Illuminate\Contracts\View\View;
use Livewire\Component;

/** Le répertoire des modules d'un tableau de bord. */
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

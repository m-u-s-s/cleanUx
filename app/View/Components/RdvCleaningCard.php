<?php

namespace App\View\Components;

use App\Models\RendezVous;
use Illuminate\View\Component;
use Illuminate\View\View;

class RdvCleaningCard extends Component
{
    public RendezVous $rdv;
    public bool $showActions;

    public function __construct(RendezVous $rdv, bool $showActions = false)
    {
        $this->rdv = $rdv;
        $this->showActions = $showActions;
    }

    public function render(): View
    {
        return view('components.rdv-cleaning-card');
    }
}
<?php

namespace App\View\Components;

use App\Models\Booking;
use Illuminate\View\Component;
use Illuminate\View\View;

class RdvCleaningCard extends Component
{
    public Booking $rdv;
    public bool $showActions;

    public function __construct(Booking $rdv, bool $showActions = false)
    {
        $this->rdv = $rdv;
        $this->showActions = $showActions;
    }

    public function render(): View
    {
        return view('components.rdv-cleaning-card');
    }
}
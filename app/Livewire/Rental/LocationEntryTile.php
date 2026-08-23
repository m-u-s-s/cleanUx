<?php

namespace App\Livewire\Rental;

use App\Services\Rental\RentalAvailability;
use Illuminate\Contracts\View\View;
use Livewire\Component;

/** LA CASE « LOCATION » DU CATALOGUE — ET SON ABSENCE QUAND IL N'Y A RIEN À LOUER. */
class LocationEntryTile extends Component
{
    public function render(): View
    {
        $disponibles = app(RentalAvailability::class)->combienDeVehiculesProposables();

        return view('livewire.rental.location-entry-tile', [
            'disponibles' => $disponibles,
        ]);
    }
}

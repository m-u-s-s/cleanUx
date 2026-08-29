<?php

use App\Livewire\PeerRental\PeerAdminCenter;
use App\Livewire\PeerRental\PeerCatalogue;
use App\Livewire\PeerRental\PeerMyRentals;
use App\Livewire\PeerRental\PeerMyVehicles;
use App\Livewire\PeerRental\PeerRentalDetail;
use App\Livewire\PeerRental\PeerVehicleEditor;
use App\Livewire\PeerRental\PeerVehiclePage;
use Illuminate\Support\Facades\Route;

/*
| LA LOCATION ENTRE MEMBRES.
|
| Rien ici ne touche « Nos locations » (`/location`, routes `location.*`), qui loue la flotte
| DE LA PLATEFORME. Ce module met deux membres en relation ; un test d'architecture echoue si
| l'un des deux emprunte a l'autre.
|
| Les ecrans ne sont ni « client » ni « prestataire » : un MEMBRE loue et prete. Un seul jeu
| de routes, sous `/dashboard/location-entre-membres`, pour tous les roles.
*/

Route::get('/louer', PeerCatalogue::class)->name('peer.catalogue');
Route::get('/louer/{vehicle}', PeerVehiclePage::class)->name('peer.vehicule');

Route::middleware(['auth', 'verified', 'active.account'])
    ->prefix('dashboard/location-entre-membres')
    ->name('peer.')
    ->group(function () {
        Route::get('/mes-locations', PeerMyRentals::class)->name('my-rentals');
        Route::get('/mes-vehicules', PeerMyVehicles::class)->name('owner.vehicles');
        Route::get('/mes-vehicules/{vehicle}', PeerVehicleEditor::class)->name('owner.vehicle');
        Route::get('/locations/{rental}', PeerRentalDetail::class)->name('rental');
    });

Route::middleware(['auth', 'verified', 'role:admin'])
    ->get('/admin/location-entre-membres', PeerAdminCenter::class)
    ->name('peer.admin');

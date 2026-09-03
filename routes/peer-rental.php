<?php

use App\Livewire\PeerRental\PeerAdminCenter;
use App\Livewire\PeerRental\PeerCatalogue;
use App\Livewire\PeerRental\PeerMyRentals;
use App\Livewire\PeerRental\PeerMyStays;
use App\Livewire\PeerRental\PeerMyVehicles;
use App\Livewire\PeerRental\PeerRentalDetail;
use App\Livewire\PeerRental\PeerStayCatalogue;
use App\Livewire\PeerRental\PeerStayEditor;
use App\Livewire\PeerRental\PeerStayPage;
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

// LE CATALOGUE DES LOGEMENTS vit a cote de celui des vehicules : un voyageur ne filtre pas un
// studio par sa boite de vitesses, et une barre de filtres a moitie applicable est pire que deux
// ecrans.
Route::get('/sejours', PeerStayCatalogue::class)->name('peer.sejours');

// LE LOGEMENT A SA PROPRE URL. Une seule route `/louer/{bien}` melangerait deux identifiants
// numeriques : la premiere annonce trouvee gagnerait, et l'autre deviendrait injoignable.
Route::get('/sejour/{stay}', PeerStayPage::class)->name('peer.sejour');

Route::middleware(['auth', 'verified', 'active.account'])
    ->prefix('dashboard/location-entre-membres')
    ->name('peer.')
    ->group(function () {
        Route::get('/mes-locations', PeerMyRentals::class)->name('my-rentals');
        Route::get('/mes-vehicules', PeerMyVehicles::class)->name('owner.vehicles');
        Route::get('/mes-vehicules/{vehicle}', PeerVehicleEditor::class)->name('owner.vehicle');

        // LES LOGEMENTS SUIVENT LE MEME CHEMIN QUE LES VEHICULES : meme prefixe, memes gardes.
        // Un membre gere ses deux biens au meme endroit, sans changer d'espace.
        Route::get('/mes-logements', PeerMyStays::class)->name('owner.stays');
        Route::get('/mes-logements/{stay}', PeerStayEditor::class)->name('owner.stay');
        Route::get('/locations/{rental}', PeerRentalDetail::class)->name('rental');
    });

Route::middleware(['auth', 'verified', 'role:admin'])
    ->get('/admin/location-entre-membres', PeerAdminCenter::class)
    ->name('peer.admin');

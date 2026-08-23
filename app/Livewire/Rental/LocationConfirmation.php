<?php

namespace App\Livewire\Rental;

use App\Models\RentalBooking;
use App\Models\RentalVehicle;
use App\Services\Rental\RentalBookingService;
use App\Services\Rental\RentalPricing;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\ValidationException;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Locked;
use Livewire\Component;

/** LE RÉCAPITULATIF DE LOCATION — LES DEUX PRIX, L'ADRESSE, ET LA VOITURE À 360°. */
#[Layout('layouts.app')]
class LocationConfirmation extends Component
{
    #[Locked]
    public string $reference = '';

    public ?string $erreur = null;

    public bool $confirmee = false;

    public function mount(string $reference): void
    {
        $this->reference = $reference;

        $location = $this->location();

        // Une réservation déjà engagée s'affiche comme telle plutôt que de reproposer un bouton
        // qui ne ferait rien.
        $this->confirmee = $location->status !== RentalBooking::STATUT_BROUILLON;
    }

    /** Bascule la garantie depuis le récapitulatif. */
    public function choisirLaProtection(string $protection): void
    {
        $location = $this->location();

        if ($location->status !== RentalBooking::STATUT_BROUILLON) {
            return;
        }

        $vehicule = $location->vehicle;
        $prix = app(RentalPricing::class)->pour($vehicule, $location->starts_at, $location->ends_at, $protection);

        $location->forceFill([
            'protection' => $protection === RentalVehicle::PROTECTION_AVEC && $vehicule->proposeUneGarantie()
                ? RentalVehicle::PROTECTION_AVEC
                : RentalVehicle::PROTECTION_SANS,
            'subtotal_cents' => $prix['subtotal_cents'],
            'waiver_total_cents' => $prix['waiver_total_cents'],
            'total_cents' => $prix['total_cents'],
            'deposit_cents' => $prix['deposit_cents'],
        ])->save();
    }

    public function confirmer(): void
    {
        $this->erreur = null;

        try {
            app(RentalBookingService::class)->confirmer($this->location(), Auth::id());
            $this->confirmee = true;
        } catch (ValidationException $e) {
            // Le service refuse pour une raison métier — permis trop récent, conducteur trop jeune,
            // véhicule pris entre-temps. Le message vient de lui : le réécrire ici le ferait
            // diverger de la règle qu'il applique.
            $this->erreur = collect($e->errors())->flatten()->first();
        }
    }

    public function render(): View
    {
        $location = $this->location();
        $vehicule = $location->vehicle;

        return view('livewire.rental.location-confirmation', [
            'location' => $location,
            'vehicule' => $vehicule,
            // Les deux hypothèses, toujours : c'est ce qui rend la garantie compréhensible.
            'devis' => app(RentalPricing::class)->devis($vehicule, $location->starts_at, $location->ends_at),
        ]);
    }

    /** La réservation, retrouvée par sa référence. LA RÉFÉRENCE SEULE NE SUFFIT PAS À DONNER ACCÈS. */
    private function location(): RentalBooking
    {
        $location = RentalBooking::query()
            ->with(['vehicle.galerie', 'vehicle.rotation360', 'vehicle.modele3d', 'vehicle.pickupPoint'])
            ->where('reference', $this->reference)
            ->firstOrFail();

        $jeton = session()->get('rental_session_token');

        $cestLeSien = ($location->client_id !== null && $location->client_id === Auth::id())
            || ($location->session_token !== null && $location->session_token === $jeton);

        abort_unless($cestLeSien, 403);

        return $location;
    }
}

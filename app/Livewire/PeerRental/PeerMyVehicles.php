<?php

namespace App\Livewire\PeerRental;

use App\Models\PeerRental;
use App\Models\PeerReview;
use App\Models\PeerVehicle;
use App\Services\PeerRental\PeerReviewService;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Collection;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Layout;
use Livewire\Component;

/** MES VEHICULES EN LOCATION — la liste, et ce qui manque a chacun pour etre publie. */
#[Layout('layouts.app')]
class PeerMyVehicles extends Component
{
    public ?string $message = null;

    /** @return Collection<int, PeerVehicle> */
    #[Computed]
    public function vehicules(): Collection
    {
        return PeerVehicle::query()
            ->with(['media', 'documents'])
            ->withCount(['rentals as locations_count'])
            ->where('owner_id', auth()->id())
            ->orderByDesc('created_at')
            ->get();
    }

    /** Les revenus deja captures, tous vehicules confondus. */
    #[Computed]
    public function revenusCents(): int
    {
        return (int) PeerRental::query()
            ->where('owner_id', auth()->id())
            ->where('payment_status', PeerRental::PAIEMENT_CAPTURE)
            ->sum('owner_payout_cents');
    }

    #[Computed]
    public function note(): ?float
    {
        return app(PeerReviewService::class)->noteMoyenne(
            auth()->user(),
            PeerReview::ROLE_PROPRIETAIRE,
        );
    }

    /**
     * UN COMPTE SANS STRIPE CONNECT NE PEUT PAS ETRE PAYE.
     *
     * On le dit AVANT la premiere annonce : le decouvrir a la remise des cles, une fois le
     * vehicule confie, serait le pire moment.
     */
    #[Computed]
    public function peutEtrePaye(): bool
    {
        return (bool) auth()->user()?->canReceiveStripeConnectPayments();
    }

    public function creer(): void
    {
        $vehicule = PeerVehicle::create([
            'reference' => PeerVehicle::genererUneReference(),
            'owner_id' => auth()->id(),
            'status' => PeerVehicle::STATUT_BROUILLON,
            'brand' => '',
            'model' => '',
            'year' => (int) now()->year,
            'plate' => '',
            'category' => 'citadine',
            'transmission' => PeerVehicle::TRANSMISSION_MANUELLE,
            'fuel' => 'essence',
            'daily_price_cents' => 4000,
            'currency' => config('fx.base_currency', 'EUR'),
        ]);

        $this->redirect(route('peer.owner.vehicle', $vehicule), navigate: true);
    }

    public function render(): View
    {
        return view('livewire.peer-rental.peer-my-vehicles');
    }
}

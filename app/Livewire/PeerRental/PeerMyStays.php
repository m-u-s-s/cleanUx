<?php

namespace App\Livewire\PeerRental;

use App\Models\PeerRental;
use App\Models\PeerReview;
use App\Models\PeerStay;
use App\Services\PeerRental\PeerReviewService;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Collection;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Layout;
use Livewire\Component;

/** MES LOGEMENTS EN LOCATION — la liste, et ce qui manque à chacun pour être publié. */
#[Layout('layouts.app')]
class PeerMyStays extends Component
{
    public ?string $message = null;

    /** @return Collection<int, PeerStay> */
    #[Computed]
    public function logements(): Collection
    {
        return PeerStay::query()
            ->with('media')
            ->withCount(['rentals as locations_count'])
            ->where('owner_id', auth()->id())
            ->orderByDesc('created_at')
            ->get();
    }

    /** Les revenus déjà capturés, tous logements confondus. */
    #[Computed]
    public function revenusCents(): int
    {
        return (int) PeerRental::query()
            ->where('owner_id', auth()->id())
            ->where('rentable_type', PeerStay::class)
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
     * On le dit AVANT la première annonce : le découvrir à l'arrivée du voyageur, une fois les
     * clés remises, serait le pire moment.
     */
    #[Computed]
    public function peutEtrePaye(): bool
    {
        return (bool) auth()->user()?->canReceiveStripeConnectPayments();
    }

    /**
     * UNE ANNONCE NAIT EN BROUILLON, jamais publiée.
     *
     * Elle n'est visible de personne tant que le propriétaire ne l'a pas complétée puis envoyée
     * en vérification — et un logement mal décrit coûte plus cher qu'un logement absent.
     */
    public function creer(): void
    {
        $logement = PeerStay::create([
            'owner_id' => auth()->id(),
            'status' => PeerStay::STATUT_BROUILLON,
            'title' => '',
            'property_type' => 'appartement',
            'space_type' => 'entire',
            'nightly_price_cents' => 6000,
            'currency' => config('fx.base_currency', 'EUR'),
        ]);

        $this->redirect(route('peer.owner.stay', $logement), navigate: true);
    }

    public function render(): View
    {
        return view('livewire.peer-rental.peer-my-stays');
    }
}

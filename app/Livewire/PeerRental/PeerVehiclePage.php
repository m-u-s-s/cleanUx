<?php

namespace App\Livewire\PeerRental;

use App\Models\PeerReview;
use App\Models\PeerVehicle;
use App\Services\PeerRental\PeerAvailability;
use App\Services\PeerRental\PeerDriverEligibility;
use App\Services\PeerRental\PeerPricing;
use App\Services\PeerRental\PeerRentalService;
use App\Services\PeerRental\PeerReviewService;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Carbon;
use Illuminate\Validation\ValidationException;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Locked;
use Livewire\Attributes\Url;
use Livewire\Component;

/**
 * LA FICHE D'UN VEHICULE, ET SA RESERVATION.
 *
 * Le devis se recalcule a chaque changement de date : le locataire voit le prix AVANT de
 * donner sa carte, et c'est ce prix-la qui est preleve.
 */
#[Layout('layouts.app')]
class PeerVehiclePage extends Component
{
    #[Locked]
    public int $vehiculeId;

    #[Url(as: 'du', except: '')]
    public string $debut = '';

    #[Url(as: 'au', except: '')]
    public string $fin = '';

    public bool $livraison = false;

    public ?string $assurance = null;

    public string $adresseLivraison = '';

    /** Le moyen de paiement vient de Stripe Elements ; il n'est jamais stocke. */
    public string $paymentMethodId = '';

    public ?string $erreur = null;

    public function mount(PeerVehicle $vehicle): void
    {
        abort_unless($vehicle->estPubliee(), 404);

        $this->vehiculeId = $vehicle->id;
    }

    #[Computed]
    public function vehicule(): PeerVehicle
    {
        return PeerVehicle::query()
            ->with(['media', 'owner:id,name,profile_photo_path,created_at'])
            ->findOrFail($this->vehiculeId);
    }

    /** @return array{debut: Carbon, fin: Carbon}|null */
    #[Computed]
    public function periode(): ?array
    {
        if ($this->debut === '' || $this->fin === '') {
            return null;
        }

        try {
            $debut = Carbon::parse($this->debut)->setTime(10, 0);
            $fin = Carbon::parse($this->fin)->setTime(10, 0);
        } catch (\Throwable) {
            return null;
        }

        return $fin->greaterThan($debut) ? ['debut' => $debut, 'fin' => $fin] : null;
    }

    /** @return array<string, mixed>|null */
    #[Computed]
    public function devis(): ?array
    {
        $periode = $this->periode();

        if ($periode === null) {
            return null;
        }

        return app(PeerPricing::class)->devis($this->vehicule(), $periode['debut'], $periode['fin'], [
            'livraison' => $this->livraison,
            'assurance' => $this->assurance,
        ]);
    }

    #[Computed]
    public function indisponibilite(): ?string
    {
        $periode = $this->periode();

        if ($periode === null) {
            return null;
        }

        return app(PeerAvailability::class)->motifDIndisponibilite(
            $this->vehicule(),
            $periode['debut'],
            $periode['fin'],
        );
    }

    /** Ce qui manque au locataire pour pouvoir reserver — dit avant, pas au moment du refus. */
    #[Computed]
    public function blocageConducteur(): ?string
    {
        $utilisateur = auth()->user();

        if ($utilisateur === null) {
            return null;
        }

        return app(PeerDriverEligibility::class)->motifDeRefus($utilisateur, $this->vehicule());
    }

    /** @return list<string> les jours pris, pour griser le calendrier */
    #[Computed]
    public function joursOccupes(): array
    {
        return app(PeerAvailability::class)->joursOccupes(
            $this->vehicule(),
            now(),
            now()->addMonths(3),
        );
    }

    #[Computed]
    public function noteDuProprietaire(): ?float
    {
        return app(PeerReviewService::class)->noteMoyenne(
            $this->vehicule()->owner,
            PeerReview::ROLE_PROPRIETAIRE,
        );
    }

    /** LA DEMANDE — les fonds sont bloques, rien n'est encaisse. */
    public function reserver(): void
    {
        $this->erreur = null;

        $utilisateur = auth()->user();

        if ($utilisateur === null) {
            $this->redirect(route('login'), navigate: true);

            return;
        }

        $periode = $this->periode();

        if ($periode === null) {
            $this->erreur = __('Choisissez vos dates.');

            return;
        }

        if (trim($this->paymentMethodId) === '') {
            $this->erreur = __('Renseignez un moyen de paiement.');

            return;
        }

        try {
            $location = app(PeerRentalService::class)->demander(
                $this->vehicule(),
                $utilisateur,
                $periode['debut'],
                $periode['fin'],
                $this->paymentMethodId,
                [
                    'livraison' => $this->livraison,
                    'assurance' => $this->assurance,
                    'adresse_livraison' => $this->livraison ? $this->adresseLivraison : null,
                ],
            );
        } catch (ValidationException $e) {
            $this->erreur = collect($e->errors())->flatten()->first();

            return;
        } catch (\Throwable $e) {
            report($e);
            $this->erreur = __('La réservation n’a pas pu aboutir. Réessayez dans un instant.');

            return;
        }

        $this->redirect(route('peer.rental', $location), navigate: true);
    }

    public function render(): View
    {
        return view('livewire.peer-rental.peer-vehicle-page');
    }
}

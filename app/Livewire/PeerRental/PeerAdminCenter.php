<?php

namespace App\Livewire\PeerRental;

use App\Models\PeerClaim;
use App\Models\PeerRental;
use App\Models\PeerVehicle;
use App\Models\PeerVehicleDocument;
use App\Services\PeerRental\PeerClaimService;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Gate;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Url;
use Livewire\Component;

/**
 * LE PILOTAGE DE LA LOCATION ENTRE MEMBRES.
 *
 * Trois files : les annonces a verifier, les papiers a valider, les retenues contestees.
 * Ce sont les seuls endroits ou la plateforme decide a la place des membres.
 */
#[Layout('layouts.app')]
class PeerAdminCenter extends Component
{
    #[Url(as: 'onglet', except: 'annonces')]
    public string $onglet = 'annonces';

    public ?string $message = null;

    public ?string $erreur = null;

    public string $motifRefus = '';

    public string $montantArbitre = '';

    public function mount(): void
    {
        // LA MEME CONDITION QUE LA CASE. Garder `isAdmin()` seul ouvrirait l'ecran a un
        // administrateur a qui le registre a justement cache l'entree.
        abort_unless(Gate::allows('manage-peer-rentals'), 403);
    }

    /** @return Collection<int, PeerVehicle> */
    #[Computed]
    public function annoncesAVerifier(): Collection
    {
        return PeerVehicle::query()
            ->with(['owner:id,name', 'media', 'documents'])
            ->where('status', PeerVehicle::STATUT_EN_REVUE)
            ->orderBy('updated_at')
            ->get();
    }

    /** @return Collection<int, PeerVehicleDocument> */
    #[Computed]
    public function papiersAValider(): Collection
    {
        return PeerVehicleDocument::query()
            ->with(['vehicle.owner:id,name'])
            ->where('status', PeerVehicleDocument::STATUT_EN_REVUE)
            ->orderBy('created_at')
            ->get();
    }

    /** @return Collection<int, PeerClaim> */
    #[Computed]
    public function retenuesContestees(): Collection
    {
        return PeerClaim::query()
            ->with(['rental.vehicle', 'rental.owner:id,name', 'rental.renter:id,name'])
            ->where('status', PeerClaim::STATUT_CONTESTEE)
            ->orderBy('created_at')
            ->get();
    }

    /** @return array<string, int> */
    #[Computed]
    public function chiffres(): array
    {
        return [
            'vehicules' => (int) PeerVehicle::query()->publiees()->count(),
            'locations_en_cours' => (int) PeerRental::query()->where('status', PeerRental::STATUT_EN_COURS)->count(),
            'commission_cents' => (int) PeerRental::query()
                ->where('payment_status', PeerRental::PAIEMENT_CAPTURE)
                ->sum('platform_fee_cents'),
            'litiges' => (int) PeerClaim::query()->where('status', PeerClaim::STATUT_CONTESTEE)->count(),
        ];
    }

    public function publier(int $vehiculeId): void
    {
        $vehicule = PeerVehicle::findOrFail($vehiculeId);

        $vehicule->forceFill([
            'status' => PeerVehicle::STATUT_PUBLIE,
            'published_at' => $vehicule->published_at ?? now(),
            'reviewed_by' => auth()->id(),
            'reviewed_at' => now(),
            'rejection_reason' => null,
        ])->save();

        $this->message = __('Annonce publiée.');
        unset($this->annoncesAVerifier, $this->chiffres);
    }

    public function refuserLAnnonce(int $vehiculeId): void
    {
        PeerVehicle::findOrFail($vehiculeId)->forceFill([
            'status' => PeerVehicle::STATUT_REFUSE,
            'reviewed_by' => auth()->id(),
            'reviewed_at' => now(),
            'rejection_reason' => $this->motifRefus === '' ? __('Annonce incomplète.') : $this->motifRefus,
        ])->save();

        $this->motifRefus = '';
        $this->message = __('Annonce refusée.');
        unset($this->annoncesAVerifier);
    }

    public function validerLePapier(int $documentId): void
    {
        PeerVehicleDocument::findOrFail($documentId)->forceFill([
            'status' => PeerVehicleDocument::STATUT_VALIDE,
            'reviewed_by' => auth()->id(),
            'reviewed_at' => now(),
        ])->save();

        $this->message = __('Document validé.');
        unset($this->papiersAValider);
    }

    public function refuserLePapier(int $documentId): void
    {
        PeerVehicleDocument::findOrFail($documentId)->forceFill([
            'status' => PeerVehicleDocument::STATUT_REFUSE,
            'reviewed_by' => auth()->id(),
            'reviewed_at' => now(),
            'rejection_reason' => $this->motifRefus === '' ? __('Document illisible ou périmé.') : $this->motifRefus,
        ])->save();

        $this->motifRefus = '';
        $this->message = __('Document refusé.');
        unset($this->papiersAValider);
    }

    /** L'ARBITRAGE — le montant accorde peut differer de celui demande. */
    public function arbitrer(int $retenueId): void
    {
        $this->erreur = null;

        try {
            $retenue = PeerClaim::findOrFail($retenueId);

            app(PeerClaimService::class)->arbitrer(
                $retenue,
                auth()->user(),
                (int) round(((float) $this->montantArbitre) * 100),
                __('Arbitrage de la plateforme.'),
            );

            $this->montantArbitre = '';
            $this->message = __('Retenue arbitrée.');
        } catch (\Throwable $e) {
            $this->erreur = $e->getMessage();
        }

        unset($this->retenuesContestees, $this->chiffres);
    }

    public function render(): View
    {
        return view('livewire.peer-rental.peer-admin-center');
    }
}

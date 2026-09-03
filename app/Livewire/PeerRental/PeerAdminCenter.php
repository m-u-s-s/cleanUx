<?php

namespace App\Livewire\PeerRental;

use App\Models\PeerClaim;
use App\Models\PeerRental;
use App\Models\PeerStay;
use App\Models\PeerVehicle;
use App\Models\PeerVehicleDocument;
use App\Services\PeerRental\PeerClaimService;
use Illuminate\Contracts\View\View;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Gate;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Url;
use Livewire\Component;

/**
 * LE PILOTAGE DE LA LOCATION ENTRE MEMBRES.
 *
 * Quatre files : les annonces de vehicules a verifier, celles de logements, les papiers a
 * valider, les retenues contestees. Ce sont les seuls endroits ou la plateforme decide a la place
 * des membres.
 *
 * L'ECRAN AGIT, IL NE LIT PAS. Publier, refuser avec un motif, suspendre, remettre en ligne,
 * arbitrer : chaque file porte le geste qu'elle appelle, sinon elle n'est qu'un rapport de plus.
 */
#[Layout('layouts.app')]
class PeerAdminCenter extends Component
{
    #[Url(as: 'onglet', except: 'annonces')]
    public string $onglet = 'annonces';

    public ?string $message = null;

    public ?string $erreur = null;

    public string $motifRefus = '';

    /** Cherche dans les trois listes de logements a la fois. */
    public string $rechercheLogement = '';

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
            ->with(['documentable'])
            ->where('status', PeerVehicleDocument::STATUT_EN_REVUE)
            ->orderBy('created_at')
            ->get();
    }

    /** @return Collection<int, PeerClaim> */
    #[Computed]
    public function retenuesContestees(): Collection
    {
        return PeerClaim::query()
            ->with(['rental.rentable', 'rental.owner:id,name', 'rental.renter:id,name'])
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
            'logements' => (int) PeerStay::query()->publiees()->count(),
            // LES DEUX FILES D'ATTENTE SEPAREES : elles n'appellent pas le meme examen.
            'logements_en_attente' => (int) PeerStay::query()->where('status', PeerStay::STATUT_EN_REVUE)->count(),
            'locations_en_cours' => (int) PeerRental::query()->where('status', PeerRental::STATUT_EN_COURS)->count(),
            'commission_cents' => (int) PeerRental::query()
                ->where('payment_status', PeerRental::PAIEMENT_CAPTURE)
                ->sum('platform_fee_cents'),
            'litiges' => (int) PeerClaim::query()->where('status', PeerClaim::STATUT_CONTESTEE)->count(),
        ];
    }

    /**
     * LES LOGEMENTS QUI ATTENDENT UNE DECISION.
     *
     * @return Collection<int, PeerStay>
     */
    #[Computed]
    public function logementsAVerifier(): Collection
    {
        return $this->cherche(PeerStay::query()->with(['owner', 'media']))
            ->where('status', PeerStay::STATUT_EN_REVUE)
            ->orderBy('created_at')
            ->get();
    }

    /**
     * LES LOGEMENTS EN LIGNE, pour pouvoir en retirer un sans attendre un signalement.
     *
     * @return Collection<int, PeerStay>
     */
    #[Computed]
    public function logementsEnLigne(): Collection
    {
        return $this->cherche(PeerStay::query()->with('owner')->withCount('rentals as sejours_count'))
            ->where('status', PeerStay::STATUT_PUBLIE)
            ->orderByDesc('published_at')
            ->limit(50)
            ->get();
    }

    /**
     * CE QUI EST SORTI DU CATALOGUE — REFUSE OU RETIRE.
     *
     * Sans cette liste, suspendre une annonce etait sans retour : elle ne figurait plus dans
     * aucun ecran, et seul un acces direct a la base pouvait la remettre en ligne.
     *
     * @return Collection<int, PeerStay>
     */
    #[Computed]
    public function logementsHorsLigne(): Collection
    {
        return $this->cherche(PeerStay::query()->with('owner'))
            ->whereIn('status', [PeerStay::STATUT_SUSPENDU, PeerStay::STATUT_REFUSE])
            ->orderByDesc('reviewed_at')
            ->limit(50)
            ->get();
    }

    /**
     * @param  Builder<PeerStay>  $requete
     * @return Builder<PeerStay>
     */
    private function cherche(Builder $requete): Builder
    {
        $terme = trim($this->rechercheLogement);

        return $requete->when($terme !== '', function (Builder $q) use ($terme) {
            $motif = '%'.$terme.'%';

            $q->where(fn (Builder $sous) => $sous
                ->where('title', 'like', $motif)
                ->orWhere('city', 'like', $motif)
                ->orWhere('reference', 'like', $motif));
        });
    }

    public function publierLeLogement(int $logementId): void
    {
        $logement = PeerStay::findOrFail($logementId);

        $logement->forceFill([
            'status' => PeerStay::STATUT_PUBLIE,
            'published_at' => $logement->published_at ?? now(),
            'reviewed_by' => auth()->id(),
            'reviewed_at' => now(),
            'rejection_reason' => null,
        ])->save();

        $this->message = __('Logement publié.');
        unset($this->logementsAVerifier, $this->logementsEnLigne, $this->logementsHorsLigne, $this->chiffres);
    }

    /**
     * REFUSER AVEC UN MOTIF, TOUJOURS.
     *
     * Un refus sans explication ecrite n'est ni corrigeable par le proprietaire, ni defendable
     * six mois plus tard : le motif par defaut ne dispense pas d'en ecrire un vrai.
     */
    public function refuserLeLogement(int $logementId): void
    {
        PeerStay::findOrFail($logementId)->forceFill([
            'status' => PeerStay::STATUT_REFUSE,
            'reviewed_by' => auth()->id(),
            'reviewed_at' => now(),
            'rejection_reason' => $this->motifRefus === '' ? __('Annonce incomplète.') : $this->motifRefus,
        ])->save();

        $this->motifRefus = '';
        $this->message = __('Logement refusé.');
        unset($this->logementsAVerifier, $this->logementsHorsLigne, $this->chiffres);
    }

    /**
     * RETIRER UN LOGEMENT DEJA EN LIGNE.
     *
     * Les sejours deja reserves continuent : suspendre une annonce ne casse pas les contrats
     * qu'elle a produits, et le voyageur qui a paye garde son logement.
     */
    public function suspendreLeLogement(int $logementId): void
    {
        PeerStay::findOrFail($logementId)->forceFill([
            'status' => PeerStay::STATUT_SUSPENDU,
            'reviewed_by' => auth()->id(),
            'reviewed_at' => now(),
        ])->save();

        $this->message = __('Logement retiré du catalogue. Les séjours en cours continuent.');
        unset($this->logementsEnLigne, $this->logementsHorsLigne, $this->chiffres);
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

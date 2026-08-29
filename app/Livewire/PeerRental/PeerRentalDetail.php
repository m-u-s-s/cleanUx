<?php

namespace App\Livewire\PeerRental;

use App\Models\PeerClaim;
use App\Models\PeerCode;
use App\Models\PeerInspection;
use App\Models\PeerRental;
use App\Services\PeerRental\PeerClaimService;
use App\Services\PeerRental\PeerRentalService;
use App\Services\PeerRental\PeerReturnCharges;
use App\Services\PeerRental\PeerReviewService;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Locked;
use Livewire\Component;
use Livewire\WithFileUploads;

/**
 * UNE LOCATION, VUE DES DEUX COTES.
 *
 * Le meme ecran sert le proprietaire et le locataire : ils font les memes gestes au meme
 * moment — etat des lieux, code, confirmation — et deux ecrans divergeraient au premier
 * changement. Ce que chacun voit et peut faire depend de son role, jamais de l'adresse.
 */
#[Layout('layouts.app')]
class PeerRentalDetail extends Component
{
    use WithFileUploads;

    #[Locked]
    public int $locationId;

    public string $codeSaisi = '';

    /** L'etat des lieux en cours de saisie. */
    public ?int $kilometrage = null;

    public ?int $carburantHuitiemes = 8;

    public string $proprete = 'propre';

    public bool $permisVerifie = false;

    public string $remarques = '';

    /** @var array<string, mixed> les photos par angle */
    public array $photos = [];

    public ?string $erreur = null;

    public ?string $message = null;

    /** Le code en clair, montre UNE FOIS a celui qui doit l'afficher. */
    public ?string $codeEnClair = null;

    public string $motifRetenue = PeerClaim::MOTIF_DOMMAGE;

    public string $montantRetenue = '';

    public string $descriptionRetenue = '';

    public int $noteAvis = 5;

    public string $commentaireAvis = '';

    public function mount(PeerRental $rental): void
    {
        $utilisateur = auth()->user();

        abort_if($utilisateur === null, 403);
        abort_unless(
            in_array($utilisateur->id, [$rental->owner_id, $rental->renter_id], true) || $utilisateur->isAdmin(),
            403
        );

        $this->locationId = $rental->id;
    }

    #[Computed]
    public function location(): PeerRental
    {
        return PeerRental::query()
            ->with(['vehicle.media', 'owner', 'renter', 'inspections.photos', 'claims', 'reviews'])
            ->findOrFail($this->locationId);
    }

    #[Computed]
    public function estLeProprietaire(): bool
    {
        return auth()->id() === $this->location()->owner_id;
    }

    #[Computed]
    public function estLeLocataire(): bool
    {
        return auth()->id() === $this->location()->renter_id;
    }

    /** La phase du moment : ce qui reste a faire, et par qui. */
    #[Computed]
    public function phase(): string
    {
        return match ($this->location()->status) {
            PeerRental::STATUT_CONFIRMEE => PeerInspection::PHASE_DEPART,
            PeerRental::STATUT_EN_COURS => PeerInspection::PHASE_RETOUR,
            default => 'aucune',
        };
    }

    #[Computed]
    public function etatDesLieux(): ?PeerInspection
    {
        $phase = $this->phase();

        return $phase === 'aucune' ? null : $this->location()->inspection($phase);
    }

    /** @return array{total_cents: int, lignes: list<array{cle: string, libelle: string, detail: string, cents: int}>} */
    #[Computed]
    public function supplements(): array
    {
        return app(PeerReturnCharges::class)->calculer($this->location());
    }

    /** LE PROPRIETAIRE ACCEPTE OU REFUSE. */
    public function accepter(): void
    {
        $this->agir(fn () => app(PeerRentalService::class)->accepter($this->location(), auth()->user()));
    }

    public function refuser(): void
    {
        $this->agir(fn () => app(PeerRentalService::class)->refuser($this->location(), auth()->user()));
    }

    public function annuler(): void
    {
        $this->agir(function (): void {
            $service = app(PeerRentalService::class);

            if ($this->estLeProprietaire()) {
                $service->seDesister($this->location(), auth()->user());
            } else {
                $service->annulerParLeLocataire($this->location(), auth()->user());
            }
        });
    }

    /**
     * L'ETAT DES LIEUX SE REMPLIT AVANT LA CONFIRMATION, ET UNE SEULE FOIS PAR PHASE.
     *
     * Les deux parties le completent ensemble, sur place : le premier qui l'ouvre le cree,
     * le second le complete. Un etat des lieux par personne rendrait la comparaison inutile.
     */
    public function enregistrerLEtatDesLieux(): void
    {
        $this->erreur = null;
        $phase = $this->phase();

        if ($phase === 'aucune') {
            $this->erreur = __('Aucun état des lieux n’est attendu à ce stade.');

            return;
        }

        $this->validate([
            'kilometrage' => ['required', 'integer', 'min:0', 'max:2000000'],
            'carburantHuitiemes' => ['required', 'integer', 'min:0', 'max:8'],
            'photos.*' => ['image', 'max:8192'],
        ]);

        $inspection = $this->location()->inspections()->firstOrNew(['phase' => $phase]);

        $inspection->fill([
            'mileage_km' => $this->kilometrage,
            'fuel_eighths' => $this->carburantHuitiemes,
            'cleanliness' => $this->proprete,
            'license_verified' => $this->permisVerifie || $inspection->license_verified,
            'notes' => trim($this->remarques) === '' ? $inspection->notes : $this->remarques,
            'created_by' => $inspection->created_by ?? auth()->id(),
        ])->save();

        foreach ($this->photos as $angle => $fichier) {
            if ($fichier === null || ! in_array($angle, PeerInspection::ANGLES_REQUIS, true)) {
                continue;
            }

            $chemin = $fichier->store('peer-inspections/'.$this->location()->reference, 'public');

            $inspection->photos()->updateOrCreate(
                ['angle' => $angle],
                [
                    'path' => $chemin,
                    // L'EMPREINTE FAIT LA PREUVE : une photo remplacee apres coup se voit.
                    'sha256' => hash_file('sha256', Storage::disk('public')->path($chemin)) ?: null,
                    'taken_at' => now(),
                    'uploaded_by' => auth()->id(),
                ]
            );
        }

        $this->photos = [];
        $this->message = __('État des lieux enregistré.');

        unset($this->etatDesLieux, $this->location);
    }

    /** LE CODE, MONTRE UNE FOIS A CELUI QUI DOIT L'AFFICHER. */
    public function afficherLeCode(): void
    {
        $this->erreur = null;

        if (! $this->estLeLocataire()) {
            $this->erreur = __('C’est au locataire d’afficher le code.');

            return;
        }

        $phase = $this->phase() === PeerInspection::PHASE_RETOUR ? PeerCode::PHASE_RETOUR : PeerCode::PHASE_REMISE;

        $this->codeEnClair = app(PeerRentalService::class)->genererLeCode($this->location(), $phase);
    }

    /** LA CONFIRMATION — la seconde declenche l'argent, jamais la premiere. */
    public function confirmer(): void
    {
        $this->agir(function (): void {
            $service = app(PeerRentalService::class);
            $utilisateur = auth()->user();

            if ($this->phase() === PeerInspection::PHASE_RETOUR) {
                $service->confirmerLeRetour($this->location(), $utilisateur, $this->codeSaisi);
            } else {
                $service->confirmerLaRemise($this->location(), $utilisateur, $this->codeSaisi);
            }

            $this->codeSaisi = '';
            $this->codeEnClair = null;
        });
    }

    /** LES SUPPLEMENTS MESURES SE PROPOSENT EN UN CLIC. */
    public function reclamerLesSupplements(): void
    {
        $this->agir(fn () => app(PeerClaimService::class)
            ->ouvrirLesSupplementsMesures($this->location(), auth()->user()));
    }

    public function ouvrirUneRetenue(): void
    {
        $this->validate([
            'montantRetenue' => ['required', 'numeric', 'min:0.01'],
            'descriptionRetenue' => ['nullable', 'string', 'max:2000'],
        ]);

        $this->agir(function (): void {
            app(PeerClaimService::class)->ouvrir(
                $this->location(),
                auth()->user(),
                $this->motifRetenue,
                (int) round((float) $this->montantRetenue * 100),
                $this->descriptionRetenue === '' ? null : $this->descriptionRetenue,
            );

            $this->montantRetenue = '';
            $this->descriptionRetenue = '';
        });
    }

    public function accepterLaRetenue(int $retenueId): void
    {
        $this->agir(function () use ($retenueId): void {
            $retenue = $this->location()->claims()->findOrFail($retenueId);
            app(PeerClaimService::class)->accepter($retenue, auth()->user());
        });
    }

    public function contesterLaRetenue(int $retenueId): void
    {
        $this->agir(function () use ($retenueId): void {
            $retenue = $this->location()->claims()->findOrFail($retenueId);
            app(PeerClaimService::class)->contester($retenue, auth()->user());
        });
    }

    public function deposerUnAvis(): void
    {
        $this->validate([
            'noteAvis' => ['required', 'integer', 'min:1', 'max:5'],
            'commentaireAvis' => ['nullable', 'string', 'max:2000'],
        ]);

        $this->agir(function (): void {
            app(PeerReviewService::class)->deposer(
                $this->location(),
                auth()->user(),
                $this->noteAvis,
                $this->commentaireAvis === '' ? null : $this->commentaireAvis,
            );

            $this->commentaireAvis = '';
        });
    }

    /** UN SEUL FILET POUR TOUTES LES ACTIONS : un refus se lit, il ne plante pas la page. */
    private function agir(callable $action): void
    {
        $this->erreur = null;
        $this->message = null;

        try {
            $action();
            $this->message = __('C’est enregistré.');
        } catch (ValidationException $e) {
            $this->erreur = collect($e->errors())->flatten()->first();
        } catch (\Throwable $e) {
            $this->erreur = $e->getMessage();
        }

        unset($this->location, $this->etatDesLieux, $this->phase, $this->supplements);
    }

    public function render(): View
    {
        return view('livewire.peer-rental.peer-rental-detail');
    }
}

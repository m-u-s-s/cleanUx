<?php

namespace App\Livewire\PeerRental;

use App\Models\PeerReview;
use App\Models\PeerStay;
use App\Services\PeerRental\PeerAvailability;
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
 * L'ANNONCE VUE PAR LE VOYAGEUR.
 *
 * Elle montre le prix RÉEL pour les dates choisies — ménage et voyageurs compris — avant toute
 * saisie de moyen de paiement. Un total qui apparaît seulement à la dernière étape est la
 * première cause d'abandon sur ce genre de plateforme, et la plus facile à éviter.
 *
 * @property-read PeerStay $logement
 * @property-read ?array<string, mixed> $devis
 */
#[Layout('layouts.app')]
class PeerStayPage extends Component
{
    #[Locked]
    public int $stayId;

    #[Url(as: 'du', except: '')]
    public string $debut = '';

    #[Url(as: 'au', except: '')]
    public string $fin = '';

    #[Url(as: 'voyageurs', except: 1)]
    public int $voyageurs = 1;

    public string $paymentMethodId = '';

    public ?string $erreur = null;

    public function mount(PeerStay $stay): void
    {
        // UNE ANNONCE NON PUBLIEE N'A PAS D'URL PUBLIQUE : la laisser lisible reviendrait a
        // exposer l'adresse d'un logement que son proprietaire n'a pas encore mis en ligne.
        abort_unless($stay->estPubliable() || $stay->owner_id === auth()->id(), 404);

        $this->stayId = (int) $stay->id;
        $this->voyageurs = max(1, min($this->voyageurs, (int) $stay->max_guests));
    }

    #[Computed]
    public function logement(): PeerStay
    {
        return PeerStay::query()->with(['media', 'owner'])->findOrFail($this->stayId);
    }

    /** @return array{debut: Carbon, fin: Carbon}|null */
    #[Computed]
    public function periode(): ?array
    {
        if ($this->debut === '' || $this->fin === '') {
            return null;
        }

        try {
            $debut = Carbon::parse($this->debut)->startOfDay();
            $fin = Carbon::parse($this->fin)->startOfDay();
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

        return app(PeerPricing::class)->devis(
            $this->logement(),
            $periode['debut'],
            $periode['fin'],
            ['voyageurs' => $this->voyageurs],
        );
    }

    /** POURQUOI CES DATES NE MARCHENT PAS — pour le dire, pas seulement refuser. */
    #[Computed]
    public function indisponibilite(): ?string
    {
        $periode = $this->periode();

        if ($periode === null) {
            return null;
        }

        return app(PeerAvailability::class)->motifDIndisponibilite(
            $this->logement(),
            $periode['debut'],
            $periode['fin'],
        );
    }

    /**
     * LES JOURS DEJA PRIS DU MOIS EN COURS ET DU SUIVANT.
     *
     * Deux mois plutot qu'un : la plupart des sejours chevauchent une fin de mois, et faire
     * cliquer pour decouvrir que la semaine suivante est prise est une deception evitable.
     *
     * @return list<string>
     */
    #[Computed]
    public function joursOccupes(): array
    {
        return app(PeerAvailability::class)->joursOccupes(
            $this->logement(),
            Carbon::now()->startOfMonth(),
            Carbon::now()->addMonth()->endOfMonth(),
        );
    }

    #[Computed]
    public function noteDuProprietaire(): ?float
    {
        return app(PeerReviewService::class)->noteMoyenne(
            $this->logement()->owner,
            PeerReview::ROLE_PROPRIETAIRE,
        );
    }

    /** LA DEMANDE — les fonds sont bloqués, rien n'est encaissé. */
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

        // ON NE LOUE PAS CHEZ SOI : sans cette garde, un propriétaire pourrait bloquer son propre
        // calendrier par une réservation et fausser sa disponibilité.
        if ($this->logement()->owner_id === $utilisateur->id) {
            $this->erreur = __('Vous ne pouvez pas réserver votre propre logement.');

            return;
        }

        if (trim($this->paymentMethodId) === '') {
            $this->erreur = __('Renseignez un moyen de paiement.');

            return;
        }

        try {
            $location = app(PeerRentalService::class)->demander(
                $this->logement(),
                $utilisateur,
                $periode['debut'],
                $periode['fin'],
                $this->paymentMethodId,
                ['voyageurs' => $this->voyageurs],
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
        return view('livewire.peer-rental.peer-stay-page');
    }
}

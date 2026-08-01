<?php

namespace App\Livewire\OrderEngine;

use App\Models\Booking;
use App\Models\OrderDraft;
use App\Services\OrderEngine\BundleComposer;
use App\Services\OrderEngine\OrderConfirmationService;
use App\Services\OrderEngine\OrderDraftManager;
use App\Support\Domain\OrderDraftStatus;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\ValidationException;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Layout;
use Livewire\Component;

/**
 * Le dernier écran : récapitulatif, devis, puis confirmation.
 *
 * C'est ICI, et seulement ici, qu'une identité est demandée. La route reste publique à dessein :
 * un visiteur non connecté doit voir son récapitulatif COMPLET, prix inclus, avant de décider de
 * créer un compte. Mettre l'authentification sur la route replacerait le formulaire d'inscription
 * devant l'estimation — c'est-à-dire devant la première cause d'abandon.
 *
 * Rien n'est perdu en se connectant : le panier est rattaché au compte au retour, parce qu'il vit
 * en base sur un jeton de session et non dans l'écran.
 */
#[Layout('layouts.app')]
class OrderConfirmation extends Component
{
    public string $sessionToken = '';

    /**
     * Référence de la commande une fois confirmée.
     *
     * Après confirmation, le panier n'est plus « ouvert » : le retrouver par jeton en ouvrirait un
     * nouveau, vide. C'est la référence qui permet de rester sur la commande qu'on vient de passer,
     * y compris après un rechargement.
     */
    public ?string $confirmedReference = null;

    public string $error = '';

    public function mount(): void
    {
        $this->sessionToken = session()->get('order_draft_token', '');
    }

    /** Le panier en cours, ou la commande confirmée si on vient de la passer. */
    #[Computed]
    public function draft(): ?OrderDraft
    {
        if ($this->confirmedReference) {
            return OrderDraft::query()
                ->where('reference', $this->confirmedReference)
                // Une commande confirmée appartient à quelqu'un : la référence seule ne suffit pas
                // à l'ouvrir, sinon deviner une référence donnerait accès à l'adresse d'un autre.
                ->where('client_id', Auth::id())
                ->first();
        }

        if (! $this->sessionToken && ! Auth::check()) {
            return null;
        }

        $draft = app(OrderDraftManager::class)->resumeOrCreate($this->sessionToken, Auth::user());

        return $draft->items()->exists() ? $draft : null;
    }

    /** Le devis consolidé : un total, et le détail par métier. */
    #[Computed]
    public function quote(): ?array
    {
        $draft = $this->draft();

        return $draft ? app(BundleComposer::class)->consolidatedQuote($draft) : null;
    }

    /**
     * Ce qui manque encore, en toutes lettres.
     *
     * Affiché plutôt que caché derrière un bouton grisé muet : un bouton inactif sans explication
     * est un cul-de-sac, et le client n'a aucun moyen de savoir quoi corriger.
     *
     * @return list<string>
     */
    #[Computed]
    public function blockers(): array
    {
        $draft = $this->draft();

        return $draft ? app(OrderConfirmationService::class)->blockers($draft) : [];
    }

    /** Les réservations issues de la commande — une par métier. */
    #[Computed]
    public function bookings(): Collection
    {
        $draft = $this->draft();

        if (! $draft || $draft->status !== OrderDraftStatus::CONVERTED) {
            return collect();
        }

        return Booking::query()
            ->where('client_id', $draft->client_id)
            ->whereIn('id', $draft->items()->get()->map(fn ($item) => $item->metadata['booking_id'] ?? null)->filter())
            ->with('employe')
            ->get();
    }

    /**
     * Où en est le paiement de chaque réservation.
     *
     * L'autorisation exige un professionnel assigné ET raccordé à Stripe : avec l'attribution
     * automatique, personne n'est désigné à la confirmation. Le client doit le LIRE, plutôt que de
     * chercher un bouton de paiement qui n'existe pas encore.
     *
     * @return array<int, array{ready: bool, reason: string|null}>
     */
    #[Computed]
    public function paymentStates(): array
    {
        $service = app(OrderConfirmationService::class);

        return $this->bookings()
            ->mapWithKeys(fn (Booking $booking) => [$booking->id => $service->paymentReadiness($booking)])
            ->all();
    }

    /** Vers la connexion, en gardant le retour ici : le panier attend, il n'est pas perdu. */
    public function goToLogin(string $target = 'login')
    {
        session()->put('url.intended', route('order.confirmation'));

        return $this->redirect(route($target === 'register' ? 'register' : 'login'), navigate: false);
    }

    public function confirm(): void
    {
        $this->error = '';
        $user = Auth::user();

        if (! $user) {
            $this->error = 'Connectez-vous pour confirmer votre commande.';

            return;
        }

        $draft = $this->draft();

        if (! $draft) {
            $this->error = 'Votre panier est vide.';

            return;
        }

        try {
            $confirmed = app(OrderConfirmationService::class)->confirm($draft, $user);
        } catch (ValidationException $e) {
            // Le message du service est déjà écrit pour être lu par le client.
            $this->error = $e->getMessage();

            return;
        }

        $this->confirmedReference = $confirmed->reference;
        unset($this->draft, $this->quote, $this->blockers, $this->bookings, $this->paymentStates);
    }

    public function render()
    {
        return view('livewire.order-engine.order-confirmation');
    }
}

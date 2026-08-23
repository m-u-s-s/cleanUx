<?php

namespace App\Livewire\OrderEngine;

use App\Models\AsapDispatchRequest;
use App\Models\Booking;
use App\Models\OrderDraft;
use App\Models\Trade;
use App\Models\User;
use App\Services\OrderEngine\BundleComposer;
use App\Services\OrderEngine\OrderConfirmationService;
use App\Services\OrderEngine\OrderDraftManager;
use App\Services\Promotion\BookingPromoCodeApplier;
use App\Support\Domain\OrderDraftStatus;
use App\Support\Domain\OrderMode;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\ValidationException;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Layout;
use Livewire\Component;

/** Le dernier écran : récapitulatif, devis, puis confirmation. */
/**
 * @property-read OrderDraft|null $draft
 * @property-read array<string, mixed>|null $quote
 * @property-read list<string> $blockers
 * @property-read Collection<int, Booking> $bookings
 * @property-read array<int, list<array<string, mixed>>> $paymentOptions
 * @property-read array<int, array{ready: bool, reason: string|null}> $paymentStates
 */
#[Layout('layouts.app')]
class OrderConfirmation extends Component
{
    public string $sessionToken = '';

    /** Référence de la commande une fois confirmée. */
    public ?string $confirmedReference = null;

    public string $error = '';

    /** LE CODE PROMO — la plateforme en émettait sans que personne puisse en saisir. */
    public string $promoCode = '';

    /** Ce que le code a donné : ni exception, ni silence. */
    public ?string $promoMessage = null;

    public bool $promoApplique = false;

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

    /** AU MOINS UNE PRESTATION DU PANIER EST-ELLE VENDUE AU TEMPS ? */
    public function panierContientDuTemps(): bool
    {
        $draft = $this->draft;

        if ($draft === null) {
            return false;
        }

        return $draft->items()
            ->whereIn('trade_id', Trade::query()->where('hourly_billing', true)->select('id'))
            ->exists();
    }

    /** Le devis consolidé : un total, et le détail par métier. */
    #[Computed]
    public function quote(): ?array
    {
        $draft = $this->draft;

        return $draft ? app(BundleComposer::class)->consolidatedQuote($draft) : null;
    }

    /**
     * Ce qui manque encore, en toutes lettres.
     *
     * @return list<string>
     */
    #[Computed]
    public function blockers(): array
    {
        $draft = $this->draft;

        return $draft ? app(OrderConfirmationService::class)->blockers($draft) : [];
    }

    /** Les réservations issues de la commande — une par métier. */
    #[Computed]
    public function bookings(): Collection
    {
        $draft = $this->draft;

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
     * Les formules de règlement, par réservation.
     *
     * @return array<int, list<array<string, mixed>>>
     */
    #[Computed]
    public function paymentOptions(): array
    {
        $service = app(OrderConfirmationService::class);

        return $this->bookings
            ->mapWithKeys(fn (Booking $booking) => [$booking->id => $service->paymentOptions($booking)])
            ->all();
    }

    /**
     * Où en est le paiement de chaque réservation.
     *
     * @return array<int, array{ready: bool, reason: string|null}>
     */
    #[Computed]
    public function paymentStates(): array
    {
        $service = app(OrderConfirmationService::class);

        return $this->bookings
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

        $draft = $this->draft;

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
        unset($this->draft, $this->quote, $this->blockers, $this->bookings, $this->paymentStates, $this->paymentOptions);

        $this->appliquerLeCodePromo($confirmed, $user);

        // En mode immédiat, l'écran d'attente EST la suite : la recherche vient d'être ouverte et quelqu'un peut accepter dans les secondes qui suivent.
        if ($confirmed->mode === OrderMode::ASAP) {
            $search = AsapDispatchRequest::query()
                ->where('order_draft_id', $confirmed->id)
                ->orWhere('booking_id', $confirmed->converted_booking_id)
                ->orderBy('id')
                ->first();

            if ($search) {
                $this->redirect(route('order.asap.search', $search->id), navigate: true);
            }
        }
    }

    /** Applique le code saisi aux réservations qui viennent d'être créées. */
    private function appliquerLeCodePromo(OrderDraft $confirmed, User $client): void
    {
        $this->promoApplique = false;
        $this->promoMessage = null;

        if (trim($this->promoCode) === '') {
            return;
        }

        $reservations = Booking::query()
            ->where('client_id', $client->id)
            ->whereIn('id', $confirmed->items()->get()
                ->map(fn ($item) => $item->metadata['booking_id'] ?? null)
                ->filter())
            ->get();

        if ($reservations->isEmpty()) {
            $this->promoMessage = __("Le code n'a pas pu être appliqué : aucune réservation à réduire.");

            return;
        }

        $applicateur = app(BookingPromoCodeApplier::class);
        $remiseTotale = 0.0;

        foreach ($reservations as $reservation) {
            try {
                $rachat = $applicateur->applyToBooking($reservation, $client, $this->promoCode);
            } catch (ValidationException $e) {
                $this->promoMessage = $e->validator->errors()->first() ?: $e->getMessage();

                return;
            }

            if ($rachat) {
                $remiseTotale += (float) ($rachat->discount_amount ?? 0);
            }
        }

        if ($remiseTotale > 0.0) {
            $this->promoApplique = true;
            $this->promoMessage = __('Code appliqué : :montant € de remise.', [
                'montant' => number_format($remiseTotale, 2, ',', ' '),
            ]);
        }
    }

    public function render()
    {
        return view('livewire.order-engine.order-confirmation');
    }
}

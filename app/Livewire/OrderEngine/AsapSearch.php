<?php

namespace App\Livewire\OrderEngine;

use App\Models\AsapDispatchRequest;
use App\Services\Dispatch\SearchOutcomeService;
use App\Services\OrderEngine\AsapDispatchService;
use App\Support\Domain\AsapStatus;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\ValidationException;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Layout;
use Livewire\Component;

/** L'écran d'attente d'une course immédiate. */
/**
 * @property-read AsapDispatchRequest|null $request
 * @property-read array{free: bool, fee_cents: int, reason: string, free_seconds_left: int|null} $cancellation
 * @property-read list<array{key: string, label: string, detail: string}> $waysForward
 * @property-read bool $timedOut
 */
#[Layout('layouts.app')]
class AsapSearch extends Component
{
    public int $requestId;

    public string $error = '';

    /** Le créneau choisi pour la conversion en rendez-vous. */
    public string $scheduledAt = '';

    public function mount(int $request): void
    {
        $this->requestId = $request;

        abort_unless($this->request !== null, 404);

        // Un défaut raisonnable : demain, même heure. Un champ vide ferait cliquer pour découvrir
        // une erreur de saisie.
        $this->scheduledAt = now()->addDay()->format('Y-m-d\TH:i');
    }

    /** La demande, vérifiée contre son propriétaire. */
    #[Computed(persist: false)]
    public function request(): ?AsapDispatchRequest
    {
        $request = AsapDispatchRequest::query()
            ->with(['trade', 'draft', 'booking', 'acceptedBy'])
            ->find($this->requestId);

        if (! $request || ! Auth::check()) {
            return null;
        }

        // LA PROPRIÉTÉ SE LIT SUR LA RÉSERVATION, avec le panier en repli.
        $reservation = $request->booking;
        $proprietaire = $reservation !== null
            ? $reservation->client_id
            : $request->draft?->client_id;

        return (int) $proprietaire === (int) Auth::id() ? $request : null;
    }

    /** Ce que l'annulation coûte MAINTENANT — affiché, avant tout clic. */
    #[Computed(persist: false)]
    public function cancellation(): array
    {
        $request = $this->request;

        return $request
            ? app(AsapDispatchService::class)->quoteCancellation($request)
            : ['free' => true, 'fee_cents' => 0, 'reason' => '', 'free_seconds_left' => null];
    }

    /** Les suites proposées quand personne ne répond. Jamais moins d'une. */
    #[Computed(persist: false)]
    public function waysForward(): array
    {
        $request = $this->request;

        return $request ? app(AsapDispatchService::class)->waysForward($request) : [];
    }

    #[Computed(persist: false)]
    public function timedOut(): bool
    {
        $request = $this->request;

        return $request ? app(AsapDispatchService::class)->hasTimedOut($request) : false;
    }

    /** Le battement de l'écran. */
    public function tick(): void
    {
        $request = $this->request;

        if ($request && app(AsapDispatchService::class)->hasTimedOut($request)) {
            app(AsapDispatchService::class)->expire($request);
        }

        unset($this->request, $this->cancellation, $this->waysForward, $this->timedOut);
    }

    public function expand(): void
    {
        if ($request = $this->request) {
            app(AsapDispatchService::class)->expand($request);
        }

        unset($this->request, $this->cancellation, $this->waysForward, $this->timedOut);
    }

    /** « CONTINUER À ATTENDRE » — la recherche repart, plus large. */
    public function retry(): void
    {
        $request = $this->request;

        if ($request && $request->status === AsapStatus::EXPIRED) {
            app(SearchOutcomeService::class)->keepWaiting($request);
        }

        unset($this->request, $this->cancellation, $this->waysForward, $this->timedOut);
    }

    /** « CONVERTIR EN RENDEZ-VOUS » — la même commande, à une heure choisie. */
    public function convertToScheduled(): void
    {
        $this->error = '';
        $request = $this->request;

        if (! $request) {
            return;
        }

        try {
            $creneau = Carbon::parse($this->scheduledAt);
        } catch (\Throwable) {
            $this->error = 'Choisissez une date et une heure valides.';

            return;
        }

        try {
            app(SearchOutcomeService::class)->convertToScheduled($request, $creneau);
        } catch (ValidationException $e) {
            $this->error = $e->getMessage();

            return;
        }

        unset($this->request, $this->cancellation, $this->waysForward, $this->timedOut);
    }

    /** « ANNULER » — proprement, et sans argent capturé. */
    public function abandon(): void
    {
        $this->error = '';
        $request = $this->request;

        if (! $request) {
            return;
        }

        app(SearchOutcomeService::class)->cancelAndRelease($request, 'Aucun professionnel trouvé');

        unset($this->request, $this->cancellation, $this->waysForward, $this->timedOut);
    }

    /** Annule, en appliquant exactement ce qui vient d'être affiché. */
    public function cancel(): void
    {
        $this->error = '';
        $request = $this->request;

        if (! $request) {
            return;
        }

        try {
            app(AsapDispatchService::class)->cancel($request, 'client');
        } catch (ValidationException $e) {
            // Une intervention commencée ne s'annule plus : on le dit, on ne fait pas semblant.
            $this->error = $e->getMessage();
        }

        unset($this->request, $this->cancellation, $this->waysForward, $this->timedOut);
    }

    public function render()
    {
        return view('livewire.order-engine.asap-search');
    }
}

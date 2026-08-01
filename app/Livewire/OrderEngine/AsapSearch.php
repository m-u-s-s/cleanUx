<?php

namespace App\Livewire\OrderEngine;

use App\Models\AsapDispatchRequest;
use App\Services\OrderEngine\AsapDispatchService;
use App\Support\Domain\AsapStatus;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\ValidationException;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Layout;
use Livewire\Component;

/**
 * L'écran d'attente d'une course immédiate.
 *
 * C'est l'écran le plus anxiogène du parcours : le client a payé de sa décision et attend, sans
 * rien contrôler. Trois choses le rendent supportable, et aucune n'est décorative.
 *
 * L'ATTENTE EST HABITÉE. Le rayon s'élargit à vue, le nombre de professionnels prévenus est REL —
 * il vient de ceux dont on connaît la position et qui exercent le métier. Un compteur qui monte
 * tout seul rassure deux minutes puis détruit la confiance au premier client qui attend pour rien.
 *
 * L'ANNULATION EST TOUJOURS VISIBLE, et son coût est ANNONCÉ AVANT le clic. Cacher le bouton pour
 * retenir quelqu'un ne le retient pas : il ferme l'onglet, et on perd la course ET le client.
 *
 * JAMAIS DE CUL-DE-SAC. Quand personne ne répond, l'écran propose d'élargir, de basculer en
 * rendez-vous, ou d'être prévenu — jamais un simple constat d'échec.
 */
#[Layout('layouts.app')]
class AsapSearch extends Component
{
    public int $requestId;

    public string $error = '';

    public function mount(int $request): void
    {
        $this->requestId = $request;

        abort_unless($this->request() !== null, 404);
    }

    /**
     * La demande, vérifiée contre son propriétaire.
     *
     * Elle porte une adresse et une position : la connaître par son identifiant ne doit pas
     * suffire à la lire.
     */
    #[Computed(persist: false)]
    public function request(): ?AsapDispatchRequest
    {
        $request = AsapDispatchRequest::query()->with(['trade', 'draft', 'acceptedBy'])->find($this->requestId);

        if (! $request || ! Auth::check() || (int) $request->draft?->client_id !== (int) Auth::id()) {
            return null;
        }

        return $request;
    }

    /** Ce que l'annulation coûte MAINTENANT — affiché, avant tout clic. */
    #[Computed(persist: false)]
    public function cancellation(): array
    {
        $request = $this->request();

        return $request
            ? app(AsapDispatchService::class)->quoteCancellation($request)
            : ['free' => true, 'fee_cents' => 0, 'reason' => '', 'free_seconds_left' => null];
    }

    /** Les suites proposées quand personne ne répond. Jamais moins d'une. */
    #[Computed(persist: false)]
    public function waysForward(): array
    {
        $request = $this->request();

        return $request ? app(AsapDispatchService::class)->waysForward($request) : [];
    }

    #[Computed(persist: false)]
    public function timedOut(): bool
    {
        $request = $this->request();

        return $request ? app(AsapDispatchService::class)->hasTimedOut($request) : false;
    }

    /**
     * Le battement de l'écran.
     *
     * L'expiration est constatée ICI plutôt que par une tâche planifiée : c'est le client qui
     * regarde, et lui seul a besoin qu'on lui propose une suite au moment où il attend.
     */
    public function tick(): void
    {
        $request = $this->request();

        if ($request && app(AsapDispatchService::class)->hasTimedOut($request)) {
            app(AsapDispatchService::class)->expire($request);
        }

        unset($this->request, $this->cancellation, $this->waysForward, $this->timedOut);
    }

    public function expand(): void
    {
        if ($request = $this->request()) {
            app(AsapDispatchService::class)->expand($request);
        }

        unset($this->request, $this->cancellation, $this->waysForward, $this->timedOut);
    }

    /** Relance une recherche expirée, plus large. */
    public function retry(): void
    {
        $request = $this->request();

        if ($request && $request->status === AsapStatus::EXPIRED) {
            app(AsapDispatchService::class)->retry($request);
        }

        unset($this->request, $this->cancellation, $this->waysForward, $this->timedOut);
    }

    /**
     * Annule, en appliquant exactement ce qui vient d'être affiché.
     *
     * Le montant n'est pas relu depuis l'écran : il est recalculé par le service, qui est la même
     * source que l'affichage. L'écran et la facture ne peuvent pas diverger.
     */
    public function cancel(): void
    {
        $this->error = '';
        $request = $this->request();

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

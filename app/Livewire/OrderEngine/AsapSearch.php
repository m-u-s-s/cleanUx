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

    /**
     * Le créneau choisi pour la conversion en rendez-vous.
     *
     * Propriété publique, donc modifiable par le navigateur — et c'est sans danger : le service
     * refuse toute date passée, et la réservation visée est celle de la recherche, elle-même
     * vérifiée contre son propriétaire à chaque lecture.
     */
    public string $scheduledAt = '';

    public function mount(int $request): void
    {
        $this->requestId = $request;

        abort_unless($this->request !== null, 404);

        // Un défaut raisonnable : demain, même heure. Un champ vide ferait cliquer pour découvrir
        // une erreur de saisie.
        $this->scheduledAt = now()->addDay()->format('Y-m-d\TH:i');
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
        $request = AsapDispatchRequest::query()
            ->with(['trade', 'draft', 'booking', 'acceptedBy'])
            ->find($this->requestId);

        if (! $request || ! Auth::check()) {
            return null;
        }

        /*
         * LA PROPRIÉTÉ SE LIT SUR LA RÉSERVATION, avec le panier en repli.
         *
         * La recherche appartient désormais à une réservation : elle peut naître d'un rendez-vous
         * converti ou d'une relance, qui n'ont pas de panier. Ne vérifier que le panier rendait ces
         * recherches-là inaccessibles à leur propre client — un 404 sur son propre écran d'attente.
         */
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

    /**
     * Le battement de l'écran.
     *
     * L'expiration est constatée ICI plutôt que par une tâche planifiée : c'est le client qui
     * regarde, et lui seul a besoin qu'on lui propose une suite au moment où il attend.
     */
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

    /**
     * « CONTINUER À ATTENDRE » — la recherche repart, plus large.
     *
     * Les exclusions ne sont PAS remises à zéro : réoffrir la course à qui vient de la refuser
     * ferait vibrer son téléphone pour rien. En revanche, ceux qui n'avaient jamais été joints —
     * hors ligne il y a trois minutes, en ligne maintenant — entrent naturellement.
     */
    public function retry(): void
    {
        $request = $this->request;

        if ($request && $request->status === AsapStatus::EXPIRED) {
            app(SearchOutcomeService::class)->keepWaiting($request);
        }

        unset($this->request, $this->cancellation, $this->waysForward, $this->timedOut);
    }

    /**
     * « CONVERTIR EN RENDEZ-VOUS » — la même commande, à une heure choisie.
     *
     * SANS RE-SAISIR NI REPAYER. La réservation existe déjà, avec son devis figé, ses réponses au
     * questionnaire et son adresse : renvoyer le client dans le parcours de commande lui ferait
     * tout recommencer, et c'est exactement le moment où l'on abandonne.
     */
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

    /**
     * « ANNULER » — proprement, et sans argent capturé.
     *
     * Distinct de `cancel()` : celui-là applique les frais annoncés d'une course ACCEPTÉE. Ici,
     * personne ne s'est déplacé — facturer une recherche infructueuse serait faire payer notre
     * propre incapacité à trouver quelqu'un.
     */
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

    /**
     * Annule, en appliquant exactement ce qui vient d'être affiché.
     *
     * Le montant n'est pas relu depuis l'écran : il est recalculé par le service, qui est la même
     * source que l'affichage. L'écran et la facture ne peuvent pas diverger.
     */
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

<?php

namespace App\Livewire\Provider;

use App\Models\MissionAssignment;
use App\Services\Dispatch\MissionDispatchService;
use App\Services\Dispatch\OfferPayloadBuilder;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Facades\Auth;
use Livewire\Attributes\Computed;
use Livewire\Component;

/**
 * LA MODALE D'OFFRE, CÔTÉ WEB — la même chose que sur le téléphone.
 *
 * Un prestataire qui travaille depuis son ordinateur n'est pas un prestataire de seconde zone : il
 * doit voir la même offre, avec le même compte à rebours, et pouvoir l'accepter aussi vite. Sans
 * cette parité, la même mission part au mobile et jamais au web, ce qui se lit comme une panne.
 *
 * ÉCOUTE TEMPS RÉEL, REPLI EN SONDAGE. `wire:poll` court sur le composant est ce qui reste quand la
 * diffusion est éteinte — et elle l'est par défaut sur ce dépôt (`config/broadcasting.php` a `null`
 * pour défaut, sans variable d'environnement). Compter sur la seule socket rendrait la modale
 * invisible en local et chez tout client mal configuré.
 *
 * LIVEWIRE NE REJOUE PAS `mount()`. Chaque action publique revérifie donc que l'offre lui
 * appartient : `assignmentId` voyage par le navigateur et peut être remplacé.
 *
 * `#[Computed]` ne met en cache que l'accès PROPRIÉTÉ : `$this->offer` mémorise, `$this->offer()`
 * réexécute le corps à chaque appel. Ces déclarations disent à l'analyse statique ce que Livewire
 * expose, et rappellent la forme à employer.
 *
 * @property-read MissionAssignment|null $offer
 * @property-read array<string, mixed>|null $payload
 */
class OfferWatcher extends Component
{
    /**
     * L'offre affichée en ce moment — vérifiée à CHAQUE action, jamais crue sur parole.
     *
     * Volontairement pas `#[Locked]` : le composant en change de lui-même au fil des offres. La
     * garde est la vérification de propriété dans `offer()`, qui filtre sur l'utilisateur connecté.
     */
    public ?int $assignmentId = null;

    public string $error = '';

    /**
     * L'offre vivante de ce prestataire, la plus urgente d'abord.
     *
     * Rendue par une requête filtrée sur `Auth::id()` : c'est CETTE clause qui garantit qu'aucune
     * offre d'autrui ne peut s'afficher, quoi que le navigateur envoie.
     */
    #[Computed(persist: false)]
    public function offer(): ?MissionAssignment
    {
        if (! Auth::check()) {
            return null;
        }

        return MissionAssignment::query()
            ->where('user_id', Auth::id())
            ->where('assignment_status', 'assigned')
            ->where(function ($q) {
                $q->whereNull('expires_at')->orWhere('expires_at', '>', now());
            })
            ->with(['mission', 'mission.booking.serviceCatalog', 'mission.booking.trade'])
            ->orderBy('expires_at')
            ->first();
    }

    /** @return array<string, mixed>|null */
    #[Computed(persist: false)]
    public function payload(): ?array
    {
        $offer = $this->offer;

        return $offer ? app(OfferPayloadBuilder::class)->build($offer) : null;
    }

    /** Le battement de l'écran : il rafraîchit l'offre et laisse mourir celles qui ont expiré. */
    public function tick(): void
    {
        unset($this->offer, $this->payload);
    }

    public function accept(int $assignmentId): void
    {
        $this->error = '';
        $assignment = $this->ownedAssignment($assignmentId);

        if (! $assignment) {
            return;
        }

        try {
            app(MissionDispatchService::class)->accept($assignment);
        } catch (\DomainException $e) {
            // « Quelqu'un a été plus rapide » n'est pas une panne : le dire autrement ferait
            // croire à un bug, et le prestataire cesserait de répondre aux offres suivantes.
            $this->error = $e->getMessage();
        }

        unset($this->offer, $this->payload);
    }

    public function decline(int $assignmentId): void
    {
        $this->error = '';
        $assignment = $this->ownedAssignment($assignmentId);

        if (! $assignment) {
            return;
        }

        try {
            app(MissionDispatchService::class)->decline($assignment, 'Refusée depuis le web');
        } catch (\DomainException $e) {
            $this->error = $e->getMessage();
        }

        unset($this->offer, $this->payload);
    }

    /**
     * L'offre demandée, SI elle appartient bien au prestataire connecté.
     *
     * Livewire ne rejoue pas `mount()` : l'identifiant qui arrive avec l'action vient du
     * navigateur. Sans cette relecture, accepter la mission d'un collègue tiendrait à changer un
     * nombre dans les outils de développement.
     */
    protected function ownedAssignment(int $assignmentId): ?MissionAssignment
    {
        if (! Auth::check()) {
            return null;
        }

        return MissionAssignment::query()
            ->whereKey($assignmentId)
            ->where('user_id', Auth::id())
            ->first();
    }

    public function render(): View
    {
        return view('livewire.provider.offer-watcher');
    }
}

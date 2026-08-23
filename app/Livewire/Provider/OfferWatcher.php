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
 * @property-read MissionAssignment|null $offer
 * @property-read array<string, mixed>|null $payload
 */
class OfferWatcher extends Component
{
    /** L'offre affichée en ce moment — vérifiée à CHAQUE action, jamais crue sur parole. */
    public ?int $assignmentId = null;

    public string $error = '';

    /** L'offre vivante de ce prestataire, la plus urgente d'abord. */
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

    /** L'offre demandée, SI elle appartient bien au prestataire connecté. */
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

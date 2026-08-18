<?php

namespace App\Livewire\Client;

use App\Models\Booking;
use App\Models\MissionChecklistItem;
use App\Models\MissionQuoteRevision;
use App\Services\Missions\MissionQuoteRevisionService;
use App\Services\Missions\MissionTodoService;
use DomainException;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Facades\Auth;
use Livewire\Attributes\Locked;
use Livewire\Component;

/**
 * « GÉRER MA MISSION » — le web fait ce que le mobile fait.
 *
 * ── POURQUOI CE COMPOSANT EXISTE ─────────────────────────────────────────────────────────────
 *
 * Le suivi web portait déjà la carte, les codes à six chiffres et les QR. Il lui manquait les deux
 * choses qui ATTENDENT une réponse : la liste de tâches du client, et le nouveau devis. Un client
 * qui suit son intervention depuis un ordinateur ne pouvait ni demander « la hotte, surtout », ni
 * répondre à une révision de prix — il devait prendre son téléphone.
 *
 * ── LES IDENTIFIANTS SONT VERROUILLÉS ────────────────────────────────────────────────────────
 *
 * Une propriété publique Livewire est retournable par le navigateur avec `$set`. Sans `#[Locked]`,
 * n'importe qui pourrait répondre à la révision d'une autre réservation en changeant un nombre dans
 * la console — c'est un piège que ce dépôt a déjà payé.
 */
class GererMaMission extends Component
{
    #[Locked]
    public int $bookingId;

    public string $nouvelleTache = '';

    #[Locked]
    public ?string $erreur = null;

    public function mount(Booking $booking): void
    {
        // LA GARDE EST ICI, PAS DANS LA VUE : un composant Livewire est une porte HTTP à part
        // entière, et l'inclure depuis une page déjà gardée ne le garde pas lui-même.
        abort_unless((int) $booking->client_id === (int) Auth::id(), 403);

        $this->bookingId = $booking->id;
    }

    public function ajouterUneTache(): void
    {
        $this->erreur = null;

        $this->validate(['nouvelleTache' => ['required', 'string', 'max:191']]);

        $mission = $this->mission();

        if ($mission === null) {
            $this->erreur = 'L’intervention n’est pas encore ouverte.';

            return;
        }

        try {
            app(MissionTodoService::class)->ajouter($mission, Auth::user(), $this->nouvelleTache);
            $this->nouvelleTache = '';
        } catch (DomainException $e) {
            // LE MOTIF DU DOMAINE, TEL QUEL : « la liste est figée depuis 10:30 » dit ce qu'un
            // « une erreur est survenue » laisserait deviner.
            $this->erreur = $e->getMessage();
        }
    }

    public function retirerUneTache(int $itemId): void
    {
        $this->erreur = null;

        $mission = $this->mission();
        $item = MissionChecklistItem::query()->find($itemId);

        if ($mission === null || $item === null) {
            return;
        }

        try {
            app(MissionTodoService::class)->retirer($mission, Auth::user(), $item);
        } catch (DomainException $e) {
            $this->erreur = $e->getMessage();
        }
    }

    public function accepterLaRevision(int $revisionId): void
    {
        $this->repondre($revisionId, accepte: true);
    }

    public function refuserLaRevision(int $revisionId, string $decision): void
    {
        $this->repondre($revisionId, accepte: false, decision: $decision);
    }

    public function render(): View
    {
        $mission = $this->mission();

        return view('livewire.client.gerer-ma-mission', [
            'mission' => $mission,
            'todo' => $mission === null ? null : app(MissionTodoService::class)->pourLeClient($mission),
            'revision' => $mission === null
                ? null
                : app(MissionQuoteRevisionService::class)->vivante($mission),
        ]);
    }

    private function repondre(int $revisionId, bool $accepte, ?string $decision = null): void
    {
        $this->erreur = null;

        $revision = MissionQuoteRevision::query()->find($revisionId);

        // L'APPARTENANCE SE VÉRIFIE ICI AUSSI : l'identifiant vient du navigateur, et le service
        // refuserait certes, mais un 500 n'explique rien à qui l'a reçu.
        if ($revision === null || (int) $revision->booking_id !== $this->bookingId) {
            $this->erreur = 'Cette proposition ne concerne pas votre réservation.';

            return;
        }

        try {
            $service = app(MissionQuoteRevisionService::class);

            $accepte
                ? $service->accepter($revision, Auth::user())
                : $service->refuser($revision, Auth::user(), (string) $decision);
        } catch (DomainException $e) {
            $this->erreur = $e->getMessage();
        }
    }

    private function mission(): ?\App\Models\Mission
    {
        return \App\Models\Mission::query()
            ->where('booking_id', $this->bookingId)
            ->latest('id')
            ->first();
    }
}

<?php

namespace App\Livewire\Client;

use App\Models\Booking;
use App\Models\MaskedCallSession;
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

    public string $consigne = '';

    #[Locked]
    public ?string $erreur = null;

    public function mount(Booking $booking): void
    {
        // LA GARDE EST ICI, PAS DANS LA VUE : un composant Livewire est une porte HTTP à part
        // entière, et l'inclure depuis une page déjà gardée ne le garde pas lui-même.
        abort_unless((int) $booking->client_id === (int) Auth::id(), 403);

        $this->bookingId = $booking->id;
        // Préremplie : le client doit VOIR ce que le prestataire lit, sinon il la réécrit à
        // l'identique en croyant que rien n'est parti.
        $this->consigne = (string) ($booking->live_access_note ?? '');
    }

    private function booking(): Booking
    {
        return Booking::query()->findOrFail($this->bookingId);
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

    /**
     * LA CONSIGNE DE DERNIÈRE MINUTE — « le digicode a changé ce matin ».
     *
     * Sans fenêtre, contrairement à la to-do list : un digicode qui change à 17 h doit pouvoir se
     * dire à 17 h, même si la liste est figée depuis longtemps. C'est le prestataire qu'elle
     * dépanne, pas le client qu'elle avantage.
     */
    public function enregistrerLaConsigne(): void
    {
        $this->erreur = null;

        $this->validate(['consigne' => ['nullable', 'string', 'max:500']]);

        $note = trim($this->consigne);

        $this->booking()->forceFill([
            'live_access_note' => $note !== '' ? $note : null,
            'live_access_note_at' => $note !== '' ? now() : null,
        ])->save();
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
            'ligne' => $mission?->booking === null ? null : $this->ligneMasquee(
                (int) $mission->booking->client_id,
                (int) ($mission->lead_provider_user_id ?? $mission->booking->employe_id),
                (int) $mission->booking->id,
            ),
        ]);
    }

    /**
     * LA LIGNE MASQUÉE — un numéro relais, jamais celui de l'autre.
     *
     * Le service existait depuis longtemps et n'était appelé de NULLE PART, ni mobile ni web. Il
     * rend `available: false` avec son motif quand la ligne n'est pas ouverte : on affiche ce motif
     * plutôt que de faire disparaître le bouton, qui ferait chercher puis appeler le support.
     *
     * @return array<string, mixed>|null
     */
    private function ligneMasquee(int $clientId, int $prestataireId, int $bookingId): ?array
    {
        $session = MaskedCallSession::query()
            ->where('booking_id', $bookingId)
            ->where('client_user_id', $clientId)
            ->where('provider_user_id', $prestataireId)
            ->where('status', MaskedCallSession::STATUS_ACTIVE)
            ->latest('id')
            ->first();

        if ($session === null || ! $session->isActive() || $session->proxy_phone_number === null) {
            return null;
        }

        return ['numero' => $session->proxy_phone_number];
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

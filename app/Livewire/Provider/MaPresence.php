<?php

namespace App\Livewire\Provider;

use App\Models\ProviderPresence;
use App\Services\Dispatch\CandidateFinder;
use App\Services\Presence\ProviderPresenceService;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Facades\Config;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Layout;
use Livewire\Component;

/**
 * MA PRÉSENCE — « est-ce que je reçois des missions, là, maintenant ? »
 *
 * Cette adresse rendait du JSON brut. Elle était pourtant une case du menu prestataire : cliquer
 * dessus affichait un objet à qui voulait savoir s'il était joignable.
 *
 * TROIS PRÉSENCES COEXISTENT DANS CE DÉPÔT, et une seule décide des missions :
 *   - `PresenceTracker` (cache) porte le statut de la MESSAGERIE ;
 *   - `provider_profiles.is_online` est le drapeau hérité, tenu en phase par le service ;
 *   - `provider_presence` est lue par {@see CandidateFinder}. C'est elle.
 *
 * L'écran ne montre donc QUE la troisième. Mélanger les deux autres recréerait exactement la
 * confusion qui fait qu'un prestataire se croit joignable sans l'être.
 *
 * ÊTRE « EN LIGNE » NE SUFFIT PAS. Le répartiteur exige trois choses ensemble : le statut, un
 * signal récent, et une position. Un prestataire en ligne depuis une heure sans battement, ou
 * sans coordonnées, est invisible — et rien ne le lui disait.
 *
 * @property-read ProviderPresence $presence
 * @property-read array{joignable: bool, motif: ?string} $verdict
 */
#[Layout('layouts.app')]
class MaPresence extends Component
{
    public ?string $message = null;

    public ?string $erreur = null;

    /** Le rayon d'intervention, en kilomètres. */
    public int $rayon = 10;

    public function mount(): void
    {
        // LA COLONNE EST NULLABLE et sans defaut en base : dix kilometres est le repli, et il
        // s'ecrit des la premiere mise en ligne.
        $this->rayon = (int) ($this->presence()->available_radius_km ?: 10);
    }

    #[Computed]
    public function presence(): ProviderPresence
    {
        return app(ProviderPresenceService::class)->presenceFor(auth()->user());
    }

    /** Le délai au-delà duquel le répartiteur cesse de croire un signal, en minutes. */
    #[Computed]
    public function fraicheurMinutes(): int
    {
        return (int) Config::get('dispatch.position_freshness_minutes', 5);
    }

    /**
     * LA SEULE QUESTION QUI COMPTE, ET SA RAISON.
     *
     * Les trois conditions sont exactement celles du répartiteur : mêmes colonnes, même seuil.
     * Les recopier approximativement ferait un écran rassurant et faux.
     *
     * @return array{joignable: bool, motif: ?string}
     */
    #[Computed]
    public function verdict(): array
    {
        $presence = $this->presence();

        if ($presence->status === ProviderPresence::STATUS_OFFLINE) {
            return ['joignable' => false, 'motif' => __('Vous êtes hors ligne : aucune mission ne vous est proposée.')];
        }

        if ($presence->status === ProviderPresence::STATUS_ON_BREAK) {
            return ['joignable' => false, 'motif' => __('Vous êtes en pause : vos missions en cours continuent, les nouvelles ne vous sont pas proposées.')];
        }

        if ($presence->status === ProviderPresence::STATUS_BUSY) {
            return ['joignable' => false, 'motif' => __('Vous êtes en mission : le répartiteur ne vous propose pas de nouvelle course.')];
        }

        if ($presence->current_lat === null || $presence->current_lng === null) {
            return ['joignable' => false, 'motif' => __('Votre position est inconnue : le répartiteur cherche par distance, il ne peut pas vous trouver.')];
        }

        if ($presence->heartbeat_at === null || $presence->heartbeat_at->lt(now()->subMinutes($this->fraicheurMinutes()))) {
            return ['joignable' => false, 'motif' => __('Votre dernier signal date de plus de :minutes minutes : le répartiteur ne le croit plus.', [
                'minutes' => $this->fraicheurMinutes(),
            ])];
        }

        return ['joignable' => true, 'motif' => null];
    }

    /**
     * PASSER EN LIGNE, AVEC SA POSITION.
     *
     * Le navigateur la fournit ; sans elle, on garde la dernière connue plutôt que d'effacer une
     * position valable. Le contrôle facial garde cette porte : son refus se rend tout seul, en
     * renvoyant vers l'écran de vérification.
     */
    public function passerEnLigne(?float $lat = null, ?float $lng = null): void
    {
        $this->erreur = null;

        app(ProviderPresenceService::class)->goOnline(
            auth()->user(),
            $lat,
            $lng,
            $this->rayonValide(),
            'web',
        );

        $this->message = __('Vous êtes en ligne.');
        // LE BATTEMENT PART D'ICI, pas du clic : la position est asynchrone, et un depart
        // premature signalerait une presence que le serveur n'a pas encore enregistree.
        $this->dispatch('presence-en-ligne');
        unset($this->presence, $this->verdict);
    }

    /** LE BATTEMENT : sans lui, le répartiteur cesse de croire le statut au bout de quelques minutes. */
    public function signaler(?float $lat = null, ?float $lng = null): void
    {
        if ($this->presence()->status === ProviderPresence::STATUS_OFFLINE) {
            return;
        }

        app(ProviderPresenceService::class)->heartbeat(auth()->user(), $lat, $lng);

        unset($this->presence, $this->verdict);
    }

    public function mettreEnPause(): void
    {
        app(ProviderPresenceService::class)->goBreak(auth()->user());

        $this->message = __('Vous êtes en pause.');
        $this->dispatch('presence-hors-ligne');
        unset($this->presence, $this->verdict);
    }

    public function passerHorsLigne(): void
    {
        app(ProviderPresenceService::class)->goOffline(auth()->user());

        $this->message = __('Vous êtes hors ligne.');
        $this->dispatch('presence-hors-ligne');
        unset($this->presence, $this->verdict);
    }

    /** LE RAYON EST UNE PROMESSE : au-delà, on ne vous propose rien. */
    public function enregistrerLeRayon(): void
    {
        $this->validate(['rayon' => ['required', 'integer', 'min:1', 'max:200']]);

        $this->presence()->update(['available_radius_km' => $this->rayon]);

        $this->message = __('Rayon enregistré.');
        unset($this->presence);
    }

    private function rayonValide(): int
    {
        return max(1, min(200, $this->rayon));
    }

    public function render(): View
    {
        return view('livewire.provider.ma-presence');
    }
}

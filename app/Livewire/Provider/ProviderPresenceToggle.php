<?php

namespace App\Livewire\Provider;

use App\Services\Provider\ProviderPresenceService;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Facades\Auth;
use Livewire\Component;

/**
 * Phase 11 — Toggle online/offline pour prestataire en mode web.
 *
 * UX :
 *   - Affiche le statut actuel (online/offline) avec bullet visuel
 *   - Bouton 1-tap pour toggle
 *   - Recherche la position GPS via JS (navigator.geolocation) au moment du go-online
 *   - Heartbeat envoyé toutes les 30 secondes par JS
 *
 * Limites web vs app native :
 *   - Geolocation API ne fonctionne qu'en HTTPS et avec permission user
 *   - Si l'onglet est en background trop longtemps, le heartbeat peut s'arrêter →
 *     la console command CleanStaleOnlinePresenceCommand bascule automatiquement
 *     en offline après 5 min sans heartbeat
 */
class ProviderPresenceToggle extends Component
{
    public bool $isOnline = false;
    public ?string $wentOnlineAt = null;
    public ?string $message = null;
    public ?string $messageType = null;

    public function mount(): void
    {
        $user = Auth::user();
        $profile = $user?->providerProfile;

        if ($profile) {
            $this->isOnline = (bool) $profile->is_online;
            $this->wentOnlineAt = $profile->went_online_at?->toIso8601String();
        }
    }

    /**
     * Appelé par le JS après que navigator.geolocation a fourni la position.
     */
    public function goOnline(float $lat, float $lng, array $meta = []): void
    {
        try {
            $profile = app(ProviderPresenceService::class)->goOnline(
                Auth::user(),
                $lat,
                $lng,
                $meta,
            );
            $this->isOnline = true;
            $this->wentOnlineAt = $profile->went_online_at?->toIso8601String();
            $this->flashMessage('🟢 Vous êtes en ligne. Vous recevrez des missions.', 'success');
            $this->dispatch('presence:online-confirmed');
        } catch (\DomainException $e) {
            $this->flashMessage($e->getMessage(), 'error');
        } catch (\Throwable $e) {
            report($e);
            $this->flashMessage('Erreur lors du passage en ligne.', 'error');
        }
    }

    public function goOffline(): void
    {
        try {
            app(ProviderPresenceService::class)->goOffline(Auth::user());
            $this->isOnline = false;
            $this->wentOnlineAt = null;
            $this->flashMessage('Vous êtes hors ligne.', 'success');
            $this->dispatch('presence:offline-confirmed');
        } catch (\DomainException $e) {
            $this->flashMessage($e->getMessage(), 'error');
        } catch (\Throwable $e) {
            report($e);
            $this->flashMessage('Erreur lors du passage hors ligne.', 'error');
        }
    }

    /**
     * Heartbeat appelé par JS toutes les 30s.
     */
    public function heartbeat(float $lat, float $lng, array $meta = []): void
    {
        if (! $this->isOnline) return;

        try {
            $profile = app(ProviderPresenceService::class)->heartbeat(
                Auth::user(),
                $lat,
                $lng,
                $meta,
            );

            if (! $profile) {
                // Profile a été basculé offline entre-temps (timeout côté serveur)
                $this->isOnline = false;
                $this->dispatch('presence:offline-confirmed');
            }
        } catch (\Throwable $e) {
            report($e);
        }
    }

    public function clearMessage(): void
    {
        $this->message = null;
        $this->messageType = null;
    }

    private function flashMessage(string $text, string $type): void
    {
        $this->message = $text;
        $this->messageType = $type;
    }

    public function render(): View
    {
        return view('livewire.provider.provider-presence-toggle');
    }
}

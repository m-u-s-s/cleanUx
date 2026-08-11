<?php

namespace App\Livewire\Client;

use App\Models\Booking;
use App\Services\TripTracking\TripTrackingService;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Facades\Auth;
use App\Services\Client\SharedTrackingService;
use Livewire\Attributes\Locked;
use Livewire\Attributes\Url;
use Livewire\Component;

/**
 * Page client live tracking — affiche carte Leaflet avec position provider.
 * Polling 10s + appel API /api/client/bookings/{id}/tracking côté JS.
 */
class ClientLiveTrackingMap extends Component
{
    #[Url]
    public ?int $bookingId = null;

    /**
     * Le lien de partage, une fois demandé.
     *
     * `#[Locked]` : c'est une URL signée valable douze heures. Une propriété publique Livewire est
     * modifiable par `$set` depuis le navigateur, et rien de ce qui vient de là ne doit pouvoir
     * remplacer un lien qu'on va copier et envoyer.
     */
    #[Locked]
    public ?string $lienPartage = null;

    public function mount(?int $bookingId = null): void
    {
        $this->bookingId = $bookingId;
    }

    /**
     * PARTAGER LE SUIVI (E3) — le patron « suivez ma course ».
     *
     * Quelqu'un commande un ménage pour sa mère et n'est pas sur place : il veut qu'elle sache
     * quand sonner, et elle n'a pas de compte. Aujourd'hui il lui téléphone, puis rappelle vingt
     * minutes plus tard parce que le prestataire est pris dans un embouteillage.
     *
     * LA RÉSERVATION EST REVÉRIFIÉE ICI. `bookingId` vient de la barre d'adresse : générer un lien
     * sans contrôle donnerait à qui devine un numéro un accès signé au suivi d'un inconnu.
     */
    public function partager(): void
    {
        $booking = $this->bookingId
            ? Booking::query()
                ->where('id', $this->bookingId)
                ->where('client_id', Auth::id())
                ->first()
            : null;

        if ($booking === null) {
            return;
        }

        $this->lienPartage = app(SharedTrackingService::class)->lienPour($booking);
    }

    public function render(): View
    {
        $user = Auth::user();
        $booking = $this->bookingId
            ? Booking::query()->where('id', $this->bookingId)->where('client_id', $user->id)->first()
            : null;

        $session = null;
        if ($booking) {
            $session = app(TripTrackingService::class)->activeSessionForBooking((int) $booking->id);
        }

        return view('livewire.client.client-live-tracking-map', [
            'booking' => $booking,
            'session' => $session,
            'validiteHeures' => SharedTrackingService::VALIDITE_HEURES,
        ])->layout('layouts.app');
    }
}

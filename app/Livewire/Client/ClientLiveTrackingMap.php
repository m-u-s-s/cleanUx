<?php

namespace App\Livewire\Client;

use App\Models\Booking;
use App\Services\Client\SharedTrackingService;
use App\Services\TripTracking\TripTrackingService;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Facades\Auth;
use Livewire\Attributes\Locked;
use Livewire\Attributes\Url;
use Livewire\Component;

/** Page client live tracking — affiche carte Leaflet avec position provider. */
class ClientLiveTrackingMap extends Component
{
    #[Url]
    public ?int $bookingId = null;

    /** Le lien de partage, une fois demandé. */
    #[Locked]
    public ?string $lienPartage = null;

    public function mount(?int $bookingId = null): void
    {
        $this->bookingId = $bookingId;
    }

    /** PARTAGER LE SUIVI (E3) — le patron « suivez ma course ». */
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
